#!/usr/bin/env php
<?php
#
# username and password needed for NetApp API
# netappApiUser and netappApiPassword will be used from GlobalConfigParameter
#
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\Plugin;
use ITdesign\Plugins\StorageTable;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;
use ITdesign\Utils\FilterThreshold;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

$username = Common::getGlobalConfigParameter('netappApiUser');
$password = Common::getGlobalConfigParameter('netappApiPassword');

$server = $host;
if ($address != "" && "$host" != "$address") {
    $server = "$address";
}


$cmd = "curl -s -k -X GET 'https://$server/api/storage/volumes/' -u '$username:$password' -H 'accept: application/hal+json'";

if ($debug) {
    fwrite (STDERR,"$cmd\n");
}

$out=[];
exec($cmd, $out, $exit);

if (is_array($out) && (count($out) < 2) || !is_array($out)) {
    print "unable to connect to $server via curl\n";
    if ($convertUnknown) {
        exit(Constants::NUMERIC_CRITICAL);
    }
    exit(Constants::NUMERIC_UNKNOWN);
}

$allVolumesImploded = implode("",$out);
$allVolumes = json_decode($allVolumesImploded, 1);

if ($allVolumes === NULL) {
    print "json_decode went wrong\n";
    if ($convertUnknown) {
        exit(Constants::NUMERIC_CRITICAL);
    }
    exit(Constants::NUMERIC_UNKNOWN);
}

$storageTable = new StorageTable(['h' => $host, 's' => $service, 'Debug' => $debug]);
$storageEntry = new CheckValue(['k' => 'storageEntry', 'h' => $host, 's' => $service, 'Description' => 'Disk', 'Debug' => $debug]);

foreach ($allVolumes['records'] as $volume) {
    $out = [];
    $exit = 0;

    $tmp = $volume["_links"]["self"]["href"];
    $cmd = "curl -s -k -X GET 'https://$server/$tmp' -u '$username:$password' -H 'accept: application/hal+json'";
    exec($cmd, $out, $exit);
    if ($exit > 0 || empty($out)) {
        continue;
    }
    $data = implode("",$out);
    $data = json_decode($data, 1);

    if (!array_key_exists('space', $data)) {
        continue;
    }

    $used = $data['space']['used'];
    $max = $data['space']['size'];

    // calculate rest of geometry and add "Summary"
    $data = Common::mbc(['Used' => $data['space']['used'], 'Max' => $data['space']['size']]);

    $th = FilterThreshold::getThreshold(array(
        'h'       => $OPT['h'],
        'section' => "disk",
        'Debug'   => "$debug"
    ));

    // for a nice output
    $textTemplate = '@{Description} is @{State} (@{FreePercent}% free @{FreeReadable}@{FreeUnit}, @{UsedPercent}% used @{UsedReadable}@{UsedUnit})';

    $storageEntry->add($data);
    $storageEntry->add([
        'Description' => $volume['name'],
        'w' => $th['w'],
        'c' => $th['c'],
        Constants::Text => $data['Summary'] ?? 'Summary not set',
        Constants::WarningText => $textTemplate,
        Constants::CriticalText => $textTemplate,
    ]);
    $storageTable->add($storageEntry->getData());
}

$storageTable->bye();

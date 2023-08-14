#!/usr/bin/env php
<?php
#
# powershell script needed in a Job
# Example cron job:
#
#  /etc/cron.d/vmware-checks | grep VMDatastores
#  */5 * * * * nagios ssh bitvise 'powershell C:/ITDESIGN/powershell-scripts/get-VMDatastore.ps1' > /opt/watchit/var/data/customer/vmware/VMDatastores.json
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

CommandLine::terminateOnEmpty($host);

$file = '/opt/watchit/var/data/customer/vmware/VMDatastores.json';
$output = file_get_contents($file);

$position = strpos($output, '[');

$fileOutput = substr($output, $position);

$allVolumes = json_decode($fileOutput, 1);

if ($allVolumes === NULL) {
    print "json_decode went wrong\n";
    exit(Constants::NUMERIC_UNKNOWN);
}

$storageTable = new StorageTable(['h' => $host, 's' => $service, 'Debug' => $debug]);
$storageEntry = new CheckValue(['k' => 'storageEntry', 'h' => $host, 's' => $service, 'Description' => 'Disk', 'Debug' => $debug]);

foreach ($allVolumes as $volume) {
    // calculate rest of geometry and add "Summary"
    $data = Common::mbc(['Free' => $volume['Freespace'], 'Max' => $volume['Capacity'], 'Factor' => 1024 * 1024]);

    $th = FilterThreshold::getThreshold(array(
        'h'       => $host,
        'section' => "disk",
        'Debug'   => $debug
    ));

    // for a nice output
    $textTemplate = '@{Description} is @{State} (@{FreePercent}% free @{FreeReadable}@{FreeUnit}, @{UsedPercent}% used @{UsedReadable}@{UsedUnit})';

    $storageEntry->add($data);
    $storageEntry->add([
        'Description' => $volume['Name'],
        Constants::Text => $data['Summary'] ?? 'Summary not set',
        Constants::WarningText => $textTemplate,
        Constants::CriticalText => $textTemplate,
        'c'  => $th["c"],
        'w'  => $th["w"]
    ]);

    $storageTable->add($storageEntry->getData());
}

$storageTable->bye();

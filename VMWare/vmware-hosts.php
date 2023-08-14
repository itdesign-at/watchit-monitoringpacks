#!/usr/bin/env php
<?php
#
# powershell script needed on bitvise server
#   C:/ITDESIGN/powershell-scripts/get-VMHost.ps1
#
#
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\StateCorrelation;
use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\Plugin;
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

if ($debug) {
    fwrite (STDERR,"$cmd\n");
}

$datacenter = substr($service, 0, -13);
$cmd = "ssh bitvise powershell C:/ITDESIGN/powershell-scripts/get-VMHost.ps1 -Datacenter $datacenter";
exec($cmd, $out, $exit);

if (empty($out)) {
    print('Command failed');
    exit(Constants::NUMERIC_UNKNOWN);
}

$output = implode('', $out);
$position = strpos($output, '[');

if (!$position) {
    $position = strpos($output, '{');
    $fileOutput = substr($output, $position);
    $vmhosts = json_decode("[$fileOutput]", 1);
} else {
    $fileOutput = substr($output, $position);
    $vmhosts = json_decode($fileOutput, 1);
}

if ($vmhosts === NULL) {
    print "json_decode went wrong\n";
    exit(Constants::NUMERIC_UNKNOWN);
}

$cv = new CheckValue([
    'k'    => 'binary',
    'h'    => $host,
    's'    => $service,
    'Debug' => $debug,
]);

$outputString = '';
foreach ($vmhosts as $host) {
    $outputString .= $host['Name'] . "<br>";
}

$cv ->add([
    'State' => Constants::OK,
    'Value' => true,
    'Text' => $outputString,
]);

$cv->bye();

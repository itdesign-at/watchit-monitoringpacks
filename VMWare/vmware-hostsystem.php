#!/usr/bin/env php
<?php
#
# powershell script needed on bitvise server
#   C:/ITDESIGN/powershell-scripts/get-VMHostsystem.ps1
#
# Code Parameter for the datacenter needs to bet set
#
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Plugins\CheckValue;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

try {
  // [* CodeParam VMWare Datacenter ; Value=example-datacenter1 *]
  $datacenter = Common::getMonitoringPackParameter($OPT,'VMWare Datacenter');
} catch (Exception $e) {
  print('Monitoring Pack Parameter not found.');
  exit(Constants::NUMERIC_UNKNOWN);
}

$cmd = "ssh bitvise-server powershell C:/ITDESIGN/powershell-scripts/get-VMHostsystem.ps1 -Datacenter $datacenter";
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

$stateColumn = array_column($vmhosts, 'State');
$connectionStateColumn = array_column($vmhosts, 'ConnectionState');

$status = 'Connected';
$value = false;
$state = Constants::WARNING;
if (array_search('Maintenance', $stateColumn)) {
  $status = 'Maintenance';
} else if (array_search('NotResponding', $stateColumn)) {
  $status = 'NotResponding';
} else if (array_search('Disconnected', $stateColumn)) {
  $status = 'Disconnected';
} else {
  $value = true;
  $state = Constants::OK;
}

$value2 = true;
$state2 = Constants::OK;
$text1 = 'Host is connected to the vsphere system';
$text2 = 'Host is not in mainteance mode';
if (array_search('Disconnected', $connectionStateColumn) ||
    array_search('NotResponding', $connectionStateColumn) ||
    array_search('Maintenance', $connectionStateColumn)) {
  $value2 = false;
  $state2 = Constants::WARNING;
  $text1 = 'Host is not connected to the vsphere host';
  $text2 = 'Host is in maintenance mode';
}

$correlation = new StateCorrelation(['h' => $host, 's' => $service, 'Debug' => $debug]);

$cv = new CheckValue([
    'k' => 'binary',
    'h' => $host,
    's' => $service . ' status',
    'Text' => 'Status: ' . $status,
    'Value' => $value,
    'State' => $state,
    'Debug' => $debug,
  ]);

$correlation->add($cv);

$cv = new CheckValue([
    'k' => 'binary',
    'h' => $host,
    's' => $service . ' connection',
    'Text'  => $text1,
    'Value' => $value2,
    'State' => $state2,
    'Debug' => $debug,
  ]);

$correlation->add($cv);

$cv = new CheckValue([
    'k' => 'binary',
    'h' => $host,
    's' => $service . ' maintenance',
    'Text'  => $text2,
    'Value' => $value2,
    'State' => $state2,
    'Debug' => $debug,
  ]);

$correlation->add($cv);

$correlation->bye();

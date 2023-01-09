#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\StorageTable;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;
use ITdesign\Utils\FilterThreshold;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

## for testing only
# $OID_USED = ".1.3.6.1.2.1.2.2.1.5.15";
# $OID_FREE = ".1.3.6.1.2.1.2.2.1.5.14";

## CISCO OIDs
$OID_USED = "1.3.6.1.4.1.9.9.48.1.1.1.5.1";
$OID_FREE = "1.3.6.1.4.1.9.9.48.1.1.1.6.1";

$storageTable = new StorageTable(['h' => $host, 's' => $service, 'Debug' => $debug]);
$memoryEntry = new CheckValue(['k' => 'storageEntry', 'h' => $host, 's' => $service, 'Description' => 'Memory', 'Debug' => $debug]);

$snmp = new Snmp($host);
$snmp->setDebug($debug);

$used = $snmp->get($OID_USED);
if (!is_numeric($used)) {
    // force NoData and terminate the program
    $memoryEntry->setUnknown($convertUnknown, $OPT[Constants::UnknownText] ?? Constants::NoDataViaSNMP);
    $storageTable->add($memoryEntry->getData());
    $storageTable->set('Text', $memoryEntry->getText());
    $storageTable->bye();
}
$free = $snmp->get($OID_FREE);
if (!is_numeric($free)) {
    // force NoData and terminate the program
    $memoryEntry->setUnknown($convertUnknown, $OPT[Constants::UnknownText] ?? Constants::NoDataViaSNMP);
    $storageTable->add($memoryEntry->getData());
    $storageTable->set('Text', $memoryEntry->getText());
    $storageTable->bye();
}

// calculate rest of geometry and add "Summary"
$data = Common::mbc(['Used' => $used, 'Free' => $free]);

$th = FilterThreshold::getThreshold(array(
    'h' => $OPT['h'],
    'section' => "memory",
    'Debug' => "$debug"
));

// for a nice output
$textTemplate = '@{Description} is @{State} (@{FreePercent}% free @{FreeReadable}@{FreeUnit}, @{UsedPercent}% used @{UsedReadable}@{UsedUnit})';

$memoryEntry->add($data);
$memoryEntry->add([
    'w' => $th['w'],
    'c' => $th['c'],
    Constants::Text => $data['Summary'] ?? 'Summary not set',
    Constants::WarningText => $textTemplate,
    Constants::CriticalText => $textTemplate,
]);
$storageTable->add($memoryEntry->getData());
$storageTable->bye();

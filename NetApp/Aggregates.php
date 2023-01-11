#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\StorageTable;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;

const Description = 'Description';

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

// $storageTable collects all storage entries, used at the
// end to publish data and get a console output
$storageTable = new StorageTable(['h' => $host, 's' => $service, 'Debug' => $debug]);

// read raw data into $snmpStorageData
$snmpStorageData = [];

$snmp = new Snmp($host);
$snmp->setDebug($debug);
$snmpStorageData = $snmp->getStorageTable();

$n = count($snmpStorageData);

if ($n < 1) {
    // write NoData to the backend - we do not have storage entries
    $storageTable->set('Text', $OPT[Constants::UnknownText] ?? Constants::NoDataViaSNMP);
    $storageTable->commit();
    print $storageTable->getOutput() . "\n";
    if ($convertUnknown) {
        exit(Constants::NUMERIC_CRITICAL);
    }
    exit(Constants::NUMERIC_UNKNOWN);
}

// set to 'ON' when no include filter is configured. Plugin::compare returns
// true when the $includeFilter parameter == 'ON' (means take every dataset).
$includeFilter = FilterThreshold::getIncludeFilter(['h' => $host, 'section' => 'disk']);
if ($includeFilter === '') {
    $includeFilter = 'ON';
}

if ($debug) {
    CheckValue::dbg(__FILE__, __FUNCTION__, $includeFilter);
}

// for a nice output
$textTemplate = '@{Description} is @{State} (@{FreePercent}% free @{FreeReadable}@{FreeUnit}, @{UsedPercent}% used @{UsedReadable}@{UsedUnit})';




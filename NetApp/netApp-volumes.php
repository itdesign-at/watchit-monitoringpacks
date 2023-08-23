#!/usr/bin/env php
<?php
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

$keyword = $OPT['k'] ?? 'binary';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

/* helper vars could be used to simulate netapp storage data from a json file */
$simulateOutputFile = "";
$simulateInputFile = "";

const OID_NetApp_storageDescription = '.1.3.6.1.4.1.789.1.5.4.1.2';
const OID_NetApp_dfHighUsedKBytes = '.1.3.6.1.4.1.789.1.5.4.1.16';
const OID_NetApp_dfLowUsedKBytes = '.1.3.6.1.4.1.789.1.5.4.1.17';
const OID_NetApp_dfHighFreeKBytes = '.1.3.6.1.4.1.789.1.5.4.1.18';
const OID_NetApp_dfLowFreeKBytes = '.1.3.6.1.4.1.789.1.5.4.1.19';
const Description = 'Description';

// helper function to print debug info to STDERR
$printStderr = function(string $msg) {
    if (true) { // set it here to false if you want to avoid messages to STDERR
        fwrite (STDERR,"$msg\n");
    }
};

$snmpStorageData=[];

// e.g. $filter = '! ( "@{Description}" re aggr0.* )';
$filter = FilterThreshold::getIncludeFilter(["h" => "$host", "section" => "disk"]);

// e.g. $appParams = {"AllowZeroSize":false,"Aggregates":true,"Snapshots":true,"Volumes":false}
try {
    $j = Common::getMonitoringPackParameter($OPT,'ApplicationParameters');
    $appParameters = json_decode($j, true);
} catch (Exception $e) {
    $printStderr ($e->getMessage());
    $appParameters = ['AllowZeroSize' => false];
}

$printStderr(print_r($appParameters,true));

// skip Disks/Volumes/Aggregates where the Size == 0
$allowZeroSize = $appParameters['AllowZeroSize'] ?? "no";
$readSnapshots = $appParameters['Snapshots'] ?? "";
$readAggregates = $appParameters['Aggregates'] ?? "";

// $storageTable collects all storage entries, used at the
// end to publish data and get a console output
$storageTable = new StorageTable(['h' => $host, 's' => $service, 'Debug' => $debug]);

if (is_file($simulateInputFile)) {
    $content = file_get_contents($simulateInputFile);
    $snmpStorageData = json_decode($content, true);
    goto readFinished;
}

# e.g. "Netapp ESV Backup": "IP": "10.109.19.171",
$snmp = new Snmp($host);
$dfEntries = $snmp->walkOid(OID_NetApp_storageDescription);

/*
$dfEntries = Array
(
    [.1.3.6.1.4.1.789.1.5.4.1.2.2] => aggr0_esv_netapp11a1
    [.1.3.6.1.4.1.789.1.5.4.1.2.3] => aggr0_esv_netapp11a1/.snapshot
    [.1.3.6.1.4.1.789.1.5.4.1.2.4] => /vol/vol0
    [.1.3.6.1.4.1.789.1.5.4.1.2.5] => /vol/vol0/.snapshot
    etc.
)
*/

foreach ($dfEntries as $oid => $description) {
    $tmp = explode('.',$oid);
    $index = end($tmp);

    switch ("$readSnapshots") {
        case "yes":
            if (!str_ends_with($description, '.snapshot')) {
                $printStderr("$description does not ends with .snapshot and is excluded (packParemeter='Snapshots')");
                continue 2;
            }
            break;
        case "no":
            if (str_ends_with($description, '.snapshot')) {
                $printStderr("$description ends with .snapshot and is excluded (packParemeter='Snapshots')");
                continue 2;
            }
            break;
    }

    switch ("$readAggregates") {
        case "yes":
            if (str_ends_with($description, '.snapshot')) {
                $printStderr("$description does not ends with .snapshot and is excluded (packParemeter='Aggregates')");
                continue 2;
            }
            break;
        case "no":
            if (!str_ends_with($description, '.snapshot')) {
                $printStderr("$description ends with .snapshot and is excluded (packParemeter='Aggregates')");
                continue 2;
            }
            break;
    }

    /**
    $dfHighTotalKBytes = $snmp->get(OID_NetApp_dfHighTotalKBytes . ".$index");
    if ($dfHighTotalKBytes < 0) {
    $dfHighTotalKBytes = correctBufferOverflow($dfHighTotalKBytes);
    }

    $dfLowTotalKBytes = $snmp->get(OID_NetApp_dfLowTotalKBytes . ".$index");
    if ($dfLowTotalKBytes < 0) {
    $dfLowTotalKBytes = correctBufferOverflow($dfLowTotalKBytes);
    }
     **/

    $dfHighFreeKBytes = $snmp->get(OID_NetApp_dfHighFreeKBytes . ".$index");
    if ($dfHighFreeKBytes < 0) {
        $dfHighFreeKBytes = correctBufferOverflow($dfHighFreeKBytes);
    }

    $dfLowFreeKBytes = $snmp->get(OID_NetApp_dfLowFreeKBytes . ".$index");
    if ($dfLowFreeKBytes < 0) {
        $dfLowFreeKBytes = correctBufferOverflow($dfLowFreeKBytes);
    }

    $dfHighUsedKBytes = $snmp->get(OID_NetApp_dfHighUsedKBytes . ".$index");
    if ($dfHighUsedKBytes < 0) {
        $dfHighUsedKBytes = correctBufferOverflow($dfHighUsedKBytes);
    }

    $dfLowUsedKBytes = $snmp->get(OID_NetApp_dfLowUsedKBytes . ".$index");
    if ($dfLowUsedKBytes < 0) {
        $dfLowUsedKBytes = correctBufferOverflow($dfLowUsedKBytes);
    }

    $entry = [];
    $entry[Description] = "$description";
    $entry['Free'] = $dfHighFreeKBytes * pow(2, 32) + $dfLowFreeKBytes;
    $entry['Used'] = $dfHighUsedKBytes * pow(2, 32) + $dfLowUsedKBytes;
    $entry['Factor'] = 1024;
    $snmpStorageData[] = Common::mbc($entry);
}

if ($simulateOutputFile != "") {
    $data = json_encode($snmpStorageData);
    file_put_contents($simulateOutputFile,$data);
}

readFinished:

if (empty($snmpStorageData)) {
    print ("no data left\n");
    exit (Constants::NUMERIC_UNKNOWN);
}

// for a nice output
$textTemplate = '@{Description} is @{State} (@{FreePercent}% free @{FreeReadable}@{FreeUnit}, @{UsedPercent}% used @{UsedReadable}@{UsedUnit})';

foreach ($snmpStorageData as $storageEntry) {

    // contains disk or memory description like "/usr" or "Swap space"
    $description = '';
    if (array_key_exists(Description, $storageEntry)) {
        $description = $storageEntry[Description];
        if ($debug) {
            CheckValue::dbg(__FILE__, __FUNCTION__, $description);
        }
    }

    // update the entry with the modified description
    $storageEntry[Description] = $description;

    if ($allowZeroSize == false && $storageEntry['Size'] === 0) {
        $printStderr("$description has zero size (packParemeter='AllowZeroSize')");
        continue;
    }

    $isExcluded = Plugin::compare($filter, $storageEntry);
    if ($isExcluded) {
        $printStderr("$description is excluded by includeFilter");
        //     continue;
    }
    if (str_contains($description, 'aggr0')) {
        continue;
    }

    $printStderr("OK use $description");

    $th = FilterThreshold::getThreshold(['h' => $host, 's' => $description, 'section' => 'disk']);

    if ($debug) {
        CheckValue::dbg(__FILE__, __FUNCTION__, $th);
    }

    $cv = new CheckValue(['k' => 'storageEntry', 'Debug' => $debug]);

    $cv->add($storageEntry);
    $cv->add([
        'h' => "$host",
        's' => "$service",
        'w' => $th['w'],
        'c' => $th['c'],
        Constants::Text => $storageEntry['Summary'] ?? 'Summary not set',
        Constants::WarningText => $textTemplate,
        Constants::CriticalText => $textTemplate,
    ]);

    // init() does the comparison logic and sets Text, etc.
    $cv->init();

    // get uppercase keys only
    $data = $cv->getData();

    // add all key/values from CheckValue to the table
    $storageTable->add($data);
}

$storageTable->bye(['section' => 'disk']);

function correctBufferOverflow($what) {
    if ($what < 0) {
        $what = $what + pow(2, 32);
    }
    return $what;
}

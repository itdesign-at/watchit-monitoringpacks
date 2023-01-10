#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\Plugin;
use ITdesign\Plugins\StorageTable;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;

const Description = 'Description';

$OPT = CommandLine::getCommandLineOptions($argv);

$keyword = $OPT['k'] ?? '';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

if ($host == '' || $keyword == '') {
    print "requires -h <host> and -k <Disk|Memory>\n";
    exit(3);
}

$storageTable = new StorageTable(['h' => $host, 's' => $service, 'Debug' => $debug]);

$in = file_get_contents("php://stdin");
if ($in === false) {
    bye($storageTable, $OPT);
}

$data = json_decode($in, true);
if ($data === null) {
    bye($storageTable, $OPT);
}

// $section is either "disk" or "memory"
$section = strtolower($keyword);

$includeFilter = FilterThreshold::getIncludeFilter(['h' => $host, 'section' => $section]);
if ($includeFilter === '') {
    $includeFilter = 'ON';
}

if ($debug) {
    CheckValue::dbg(__FILE__, __FUNCTION__, $includeFilter);
}

$textTemplate = '@{Description} is @{State} (@{FreePercent}% free @{FreeReadable}@{FreeUnit}, @{UsedPercent}% used @{UsedReadable}@{UsedUnit})';

foreach ($data as $storageEntry) {

    // contains disk or memory description like "/usr" or "Swap space"
    $description = '';
    if (array_key_exists(Description, $storageEntry)) {
        $description = $storageEntry[Description];
        if ($debug) {
            CheckValue::dbg(__FILE__, __FUNCTION__, $description);
        }
    }

    // check include filter - take all storage entries if not configured
    $shouldBeIncluded = false;
    try {
        $shouldBeIncluded = Plugin::compare($includeFilter, $storageEntry);
    } catch (Exception $e) {
        // do nothing -> $shouldBeIncluded stays false
    }
    if ($shouldBeIncluded === false) {
        continue;
    }

    // update the entry with the modified description
    $storageEntry[Description] = $description;

    $th = FilterThreshold::getThreshold(['h' => $host, 's' => $description, 'section' => $section]);

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

$storageTable->bye(['section' => $section]);


function bye(StorageTable $storageTable, array $OPT) {
    $convertUnknown = $OPT['convertUnknown'] ?? false;

    // construct a syntactical storage table with one UNKNOWN entry which
    // forces to write "NoData" to the broker
    $storageTable->table = [
        [
            Constants::Exit => Constants::NUMERIC_UNKNOWN,
            Constants::UnknownText => $OPT[Constants::UnknownText] ?? 'no valid data',
        ]
    ];
    $storageTable->bye(['stay' => true]);
    if ($convertUnknown) {
        exit(Constants::NUMERIC_CRITICAL);
    }
    exit(Constants::NUMERIC_UNKNOWN);
}


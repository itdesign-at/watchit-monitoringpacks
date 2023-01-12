#!/usr/bin/env php
<?php
/**
 * Read disk/memory entries from STDIN (from json Array) or from a file
 * given with option -f fileName
 *
 * Source code and doc:
 *
 * https://github.com/itdesign-at/watchit-monitoringpacks/blob/main/plugins/disk_mem.md
 *
 */

require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\Plugin;
use ITdesign\Plugins\StorageTable;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;
use ITdesign\Utils\FilterThreshold;

const Description = 'Description';

$OPT = CommandLine::getCommandLineOptions($argv);

$keyword = $OPT['k'] ?? '';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

if ($keyword == '') {
    if (str_contains(strtolower($service), "mem")) {
        $keyword = "memory";
    } else {
        $keyword = "disk";
    }
}

$storageTable = new StorageTable(['h' => $host, 's' => $service, 'Debug' => $debug]);

if (array_key_exists('f', $OPT)) {
    $in = file_get_contents($OPT['f']);
} else {
    $in = file_get_contents("php://stdin");
}

if ($in === false) {
    $storageTable->byeNoData([
        'Text' => $OPT['UnknownText'] ?? 'no valid data input data',
        'convertUnknown' => $OPT['convertUnknown'] ?? false
    ]);
}

$data = json_decode($in, true);
if ($data === null) {
    $storageTable->byeNoData([
        'Text' => $OPT['UnknownText'] ?? 'unable to decode json input',
        'convertUnknown' => $OPT['convertUnknown'] ?? false
    ]);
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

    if (!array_key_exists(Description, $storageEntry)) {
        continue;
    }

    $storageEntry = Common::mbc($storageEntry);

    $description = $storageEntry[Description];

    // check include filter - take all storage entries if not configured
    if ($includeFilter != 'ON' && !Plugin::compare($includeFilter, $storageEntry)) {
        continue;
    }

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

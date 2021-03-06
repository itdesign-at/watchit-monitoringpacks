#!/usr/bin/env php
<?php
/**
 * Read disk or mem datasets with SNMP from hrStorageTable
 * OID 1.3.6.1.2.1.25.2.3
 *
 * Tested with PHP 8.1.7 on macOS Monterey 12.4 and
 * Ubuntu 22.04 with PHP 8.1.2
 *
 * ported from file watchit-NetDisk.php
 * Werner.Neunteufl@itdesign.at, 2022-07-12
 */
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Plugin;
use ITdesign\Plugins\StorageTable;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;

if (str_contains($argv[0], "disk")) {
    $section = "disk";
} else {
    $section = "memory";
}

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
if ($host === '') {
    print "host is empty or missing\n";
    exit(3);
}

$snmpStorageData = [];
try {
    // param $host only, snmp data are read from the json exported file
    // hides security relevant params like community string, etc.
    $snmp = new Snmp($host);
    $snmpStorageData = $snmp->getStorageTable();
} catch (Exception $e) {
    // do nothing -> $storageTable stays empty
}

if (count($snmpStorageData) < 1) {
    // TODO: write "NoData" or "NULL" to measurements
    print "no snmp answer\n";
    exit(3);
}

// set to 'ON' when no include filter is configured. Plugin::compare returns
// true when the $includeFilter parameter == 'ON' (means take every dataset).
$includeFilter = FilterThreshold::getIncludeFilter(['h' => $host, 'section' => $section]);
if ($includeFilter === '') {
    $includeFilter = 'ON';
}

// for a nice output
$textTemplate = '@{Description} is @{State} (@{FreePercent}% free @{FreeReadable}@{FreeUnit}, @{UsedPercent}% used @{UsedReadable}@{UsedUnit})';

// $storageTable collects all storage entries, used at the
// end to publish data and get a console output
$storageTable = new StorageTable($OPT);

foreach ($snmpStorageData as $storageEntry) {

    // contains disk or memory description like "/usr" or "Swap space"
    $description = '';
    if (array_key_exists('Description', $storageEntry)) {
        $description = $storageEntry['Description'];
    }

    // check SNMP hrStorageType field which determines 'disk' or 'memory' dataset
    if ($section === 'disk') {
        if (!isDisk($storageEntry)) {
            continue;
        }
        // shorten removes "Label:" and "Serial Number" from windows disk(s)
        $description = shorten($description);
    } else {
        // $section === 'memory'
        if (isDisk($storageEntry)) {
            continue;
        }
        // uppercase renames "memory" to "Memory" to have linux and
        // windows the same notation
        $description = uppercase($description);
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

    $th = FilterThreshold::getThreshold(['h' => $host, 's' => $description, 'section' => $section]);

    $cv = new CheckValue($storageEntry);
    $cv->add([
        'h' => "$host",
        's' => "$description",
        'w' => $th['w'],
        'c' => $th['c'],
        'Text' => $storageEntry['Summary'] ?? 'Summary not set',
        'WarningText' => $textTemplate,
        'CriticalText' => $textTemplate,
    ]);

    // init() does the comparison logic
    $cv->init();

    // add all key/values from CheckValue to the table
    $storageTable->add($cv->getMacros());
}

$storageTable->bye();

/**
 * isDisk check disks only - see SNMP Type definition
 * https://datatracker.ietf.org/doc/html/rfc2790#page-9
 *
 * @param array $input
 * @return bool
 */
function isDisk(array $input): bool
{
    $type = $input['Type'] ?? '';
    if ($type === '') {
        return true;
    }
    // ported from old DataRetriever.class.php
    foreach (['FixedDisk', 'NetworkDisk', 'CompactDisc'] as $t) {
        if ($type === $t) {
            return true;
        }
    }
    return false;
}

/**
 * shorten converts
 *      C:\ Label:  Serial Number f653e1fc ==> C:\
 *      D:\ ==> D:\
 *      I:\ Label:IDM_DB  Serial Number f8e96850 ==> I:\ IDM_DB
 * @param string $description
 * @return string
 */
function shorten(string $description): string
{
    // ported from MP_Net.class.php
    if (str_contains($description, ' Label:')) {
        $description = rtrim(str_replace('Label:', '', $description));
    }
    if (str_contains($description, 'Serial Number ')) {
        $description = rtrim(preg_replace('/Serial Number\s+.*/', '', $description));
    }
    return $description;
}

/**
 * uppercase converts "memory" into "Memory" to keep linux and windows
 * notations the same.
 *      Windows example: Virtual Memory
 *      Linux example: Virtual memory
 * @param string $description
 * @return string
 */
function uppercase(string $description): string
{
    $description = str_replace("memory", "Memory", $description);
    return $description;
}


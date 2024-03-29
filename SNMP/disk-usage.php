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
 *
 * 2023-09-23: check 'Summary' key on $storageEntry to
 *             avoid eval problems
 */
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\Plugin;
use ITdesign\Plugins\StorageTable;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;

const Description = 'Description';

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

// check if called via -conf <package file> or directly
// must be named "disk-*.php" like "disk-usage.php", memory could be a symlink
if (str_contains($OPT['conf'] ?? '', 'disk') || str_contains($argv[0], 'disk')) {
    $section = 'disk';
} else {
    $section = 'memory';
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
    $storageTable->byeNoData([
        'Text' => Constants::NoDataViaSNMP,
        'convertUnknown' => $OPT['convertUnknown'] ?? false
    ]);
}

// set to 'ON' when no include filter is configured. Plugin::compare returns
// true when the $includeFilter parameter == 'ON' (means take every dataset).
$includeFilter = FilterThreshold::getIncludeFilter(['h' => $host, 'section' => $section]);
if ($includeFilter === '') {
    $includeFilter = 'ON';
}

if ($debug) {
    CheckValue::dbg(__FILE__, __FUNCTION__, $includeFilter);
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
        // windows the same description
        $description = uppercase($description);
    }

    // update the entry with the modified description
    $storageEntry[Description] = $description;

    // The 'Summary' key is generated in mbc() at the end of disk/mem calculation
    // and is an indicator that everything worked fine - so check it here before Plugin::compare below
    // which evals ...
    if (!array_key_exists('Summary', $storageEntry)) {
            continue;
    }

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

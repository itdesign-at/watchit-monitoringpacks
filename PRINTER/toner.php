#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Utils\CommandLine;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host           = $OPT['h'] ?? '';
$service        = $OPT['s'] ?? '';
$debug          = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

const TONER_OID = ".1.3.6.1.2.1.43.11.1.1.6.1";
const TONER_MAX = ".1.3.6.1.2.1.43.11.1.1.8.1.";
const TONER_CUR = ".1.3.6.1.2.1.43.11.1.1.9.1.";


// 10% warning
$warn = 0.1;
// 1% critical
$crit = 0.01;

$correlation = new StateCorrelation([
    'h' => $host,
    's' => $service,
    'Debug' => $debug
]);

$snmp = new Snmp($host);

$list = $snmp->walkOid(TONER_OID);

if (empty($list)) {
    exitWithError("SNMP connection failed", 3);
}

foreach ($list as $oid => $tonerName) {
    $id = basename(str_replace(TONER_OID . '.', '', $oid));

    // Skip Waste Toner
    if ($id == 5) {
        continue;
    }

    $max = $snmp->get(TONER_MAX.$id);
    $cur = $snmp->get(TONER_CUR.$id);

    $rc = get_status($max, $cur, $warn, $crit);
    $status = getReadableStatus($rc);

    if ($max > 0 && $cur >= 0) {
        $value = ($cur / $max) * 100;
    } else {
        $value = 0;
    }

    $text = $tonerName . ' ' . sprintf('%2d', $value) . "% is $status";

    $checkValue = new CheckValue([
        'k'     => 'totalPercentage',
        'h'     => $host,
        's'     => $tonerName,
        'Text'  => $text,
        'State' => $status,
        'Value' => $value
    ]);

    $correlation->add($checkValue);
}

$correlation->bye();

/* ========================================================
   HELPER FUNCTIONS
   ======================================================== */
function get_status($max, $cur, $warn, $crit)
{
    if (($cur == 0 || $cur == -2) && $warn <= 0 && $crit <= 0) {
        return 0;
    }

    if ($cur > 0 && $max > 0) {
        $status = $cur / $max;

        if ($status > 1 || $status < 0) {
            return 3;
        }

        if ($status > $warn) {
            return 0;
        }

        if ($status > $crit) {
            return 1;
        }

        return 2;
    } else {
        if ($cur == -3) {
            return 0;
        } elseif ($cur == -2) {
            return 1;
        } elseif ($cur == 0) {
            return 2;
        } else {
            return 3;
        }
    }

    return 3;
}

function getReadableStatus($rc)
{
    if ($rc === 0) {
        return "OK";
    } elseif ($rc === 1) {
        return "WARNING";
    } elseif ($rc === 2) {
        return "CRITICAL";
    } else {
        return "UNKNOWN";
    }
}

function exitWithError($msg, $code)
{
    print "$msg\n";
    exit($code);
}


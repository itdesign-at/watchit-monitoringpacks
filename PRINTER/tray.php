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

const TRAY_NAME_OID = ".1.3.6.1.2.1.43.8.2.1.13.1";
const TRAY_MAX_OID = ".1.3.6.1.2.1.43.8.2.1.9.1";
const TRAY_CUR_OID = ".1.3.6.1.2.1.43.8.2.1.10.1";

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

$trayList = $snmp->walkOid(TRAY_NAME_OID);

if (empty($trayList)) {
    exitWithError("SNMP connection failed", 3);
}

foreach ($trayList as $oid => $trayName) {
    $id = basename(str_replace(TRAY_NAME_OID . '.', '', $oid));

    // Skip Stack Bypass
    if ($id == 1) {
        continue;
    }

    $max = $snmp->get(TRAY_MAX_OID . ".$id");
    $cur = $snmp->get(TRAY_CUR_OID . ".$id");

    $rc = get_status($max, $cur, $warn, $crit);
    $status = getReadableStatus($rc);

    if ($status === "OK") {
        $text = "$trayName Status: Paper remaining";
    } else {
        $text = "$trayName Status: No Paper remaining";
        $status = "WARNING";
    }

    $checkValue = new CheckValue([
        'h'     => $host,
        's'     => $trayName,
        'Text'  => $text,
        'State' => $status,
        'Value' => ($status === "OK") ? 1 : 0
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


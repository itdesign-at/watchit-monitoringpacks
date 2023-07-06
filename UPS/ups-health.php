#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Net\Snmp;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);


// for debugging
$printStderr = function (string $msg) {
    if (false) {
        fwrite(STDERR, "$msg\n");
    }
};

$correlation = new StateCorrelation(['h' => $host, 's' => $service, 'Debug' => $debug]);

$oid = '.1.3.6.1.2.1.33.1.2.1.0';
$snmp = new Snmp($host);
$status = $snmp->get($oid);

// Check if SNMP is responding
if ($status == Snmp::$ERROR) {
    $cv = new CheckValue([
        'k' => 'binary',
        'h' => $host,
        's' => $service,
        'Text' => Constants::NoDataViaSNMP,
        'State' => Constants::UNKNOWN,
        'Debug' => $debug
    ]);
    $cv->bye();
}

# -----------------------------------------------------------------------------
# --- Remaining Battery status ------------------------------------------------
# -----------------------------------------------------------------------------
$upsBatteryStatus = array(
    '1' => 'unknown',
    '2' => 'normal',
    '3' => 'low',
    '4' => 'depleted'
);

if (array_key_exists($status, $upsBatteryStatus)) {
    $sReadable = $upsBatteryStatus[$status];
} else {
    $sReadable = 'wrong SNMP response';
}

if ("$sReadable" == 'normal') {

}
$state = Constants::OK;
$text = sprintf('Battery is in state "%s"', $sReadable);
$value = true;

if ("$sReadable" !== 'normal') {
    $state = Constants::CRITICAL;
    $value = false;
}

$cv = new CheckValue([
    'k' => 'binary',
    'h' => $host,
    's' => 'Battery Status',
    'State' => Constants::OK,
    'Text' => $text,
    'Value' => $value,
    'Debug' => $debug
]);

$correlation->add($cv);

# -----------------------------------------------------------------------------
# --- Battery Capacity --------------------------------------------------------
# -----------------------------------------------------------------------------
$oid = '.1.3.6.1.2.1.33.1.2.4.0';
$capacity = $snmp->get($oid);

$state = Constants::OK;
if (intval($capacity) < 80) {
    $state = Constants::CRITICAL;
} else if (intval($capacity) < 95) {
    $state = Constants::WARNING;
}

$cv = new CheckValue([
    'k' => 'gauge',
    'h' => $host,
    's' => 'Battery Capacity',
    'State' => $state,
    'Text' => sprintf('Battery capacity: %d%%', $capacity),
    'Value' => intval($capacity),
    'Debug' => $debug
]);

$correlation->add($cv);

# -----------------------------------------------------------------------------
# --- Seconds on Battery ------------------------------------------------------
# -----------------------------------------------------------------------------
$oid = '.1.3.6.1.2.1.33.1.2.2.0';
$seconds = $snmp->get($oid);

$state = Constants::OK;
if (intval($seconds) > 5) {
    $state = Constants::CRITICAL;
} else if (intval($seconds) > 1) {
    $state = Constants::WARNING;
}

$cv = new CheckValue([
    'k' => 'gauge',
    'h' => $host,
    's' => 'Seconds on Battery',
    'State' => $state,
    'Text' => sprintf('Seconds on battery: %d', $seconds),
    'Value' => intval($seconds),
    'Debug' => $debug
]);

$correlation->add($cv);

# -----------------------------------------------------------------------------
# --- Remaining Battery life --------------------------------------------------
# -----------------------------------------------------------------------------
$oid = '.1.3.6.1.2.1.33.1.2.3.0';
$upsBatteryLife = $snmp->get($oid);

$state = Constants::OK;
if (intval($upsBatteryLife) < 20) {
    $state = Constants::CRITICAL;
} else if (intval($upsBatteryLife) < 25) {
    $state = Constants::WARNING;
}

$cv = new CheckValue([
    'k' => 'gauge',
    'h' => $host,
    's' => 'Remaining Battery Life',
    'State' => $state,
    'Text' => "$upsBatteryLife Minutes",
    'Value' => intval($upsBatteryLife),
    'Debug' => $debug,
]);

$correlation->add($cv);

# -----------------------------------------------------------------------------
# --- Battery Voltage --------------------------------------------------
# -----------------------------------------------------------------------------
$oid = '.1.3.6.1.2.1.33.1.2.5.0';
$upsBatteryVoltage = $snmp->get($oid);

if (!$upsBatteryVoltage == Snmp::$ERROR) {
    $batteryV = $upsBatteryVoltage * 0.1;

    $cv = new CheckValue([
        'k' => 'gauge',
        'h' => "$host",
        's' => "Battery Voltage",
        'Text' => "$batteryV V",
        'Value' => intval($batteryV),
        'Debug' => $debug,
    ]);
    $correlation->add($cv);
}

# -----------------------------------------------------------------------------
# --- Battery Current --------------------------------------------------
# -----------------------------------------------------------------------------
$oid = '.1.3.6.1.2.1.33.1.2.6.0';
$upsBatteryCurrent = $snmp->get($oid);

if (!$upsBatteryCurrent == Snmp::$ERROR) {
    $batteryC = $upsBatteryCurrent * 0.1;

    $cv = new CheckValue(array(
        'k' => "gauge",
        'h' => "$host",
        's' => "Battery Current",
        'Text' => "$batteryC A",
        'Value' => intval($batteryC),
        'Debug' => $debug,
    ));
    $correlation->add($cv);
}

$correlation->bye();

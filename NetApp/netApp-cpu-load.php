#!/usr/bin/env php
<?php
#
# Query NetApp devices and read their cpu-usage
#
# http://www.circitor.fr/Mibs/Html/NETWORK-APPLIANCE-MIB.php
#
#
# Example threshold definition in the WATCHIT UI:
# -----------------------------------------------
# Name: cpu-load Netapp Thresholds
# Section: CPU
# Warning Threshold: @{LoadAverage} gt 80%

require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$keyword = $OPT['k'] ?? '';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

$cv = new CheckValue([
    'k' => 'gauge',
    'h' => "$host",
    's' => "$service",
    'Debug' => $debug,
]);

$snmp = new Snmp($host);
$cpu = $snmp->get("1.3.6.1.4.1.789.1.2.1.3.0");

if ($cpu == Snmp::$ERROR) {
    if ($convertUnknown) {
        $cv->add([
            'State' => Constants::CRITICAL,
            'Text' => Constants::NoDataViaSNMP,
        ]);
    } else {
        $cv->add([
            'Text' => Constants::NoDataViaSNMP,
            'State' => Constants::UNKNOWN,
        ]);
    }
    $cv->bye();
}

$th = FilterThreshold::getThreshold(array(
    'h' => $host,
    'section' => "cpu",
    'Debug' => "$debug"
));

$cv->add([
    'w' => $th['w'],
    'c' => $th['c'],
    'Value' => "$cpu",
    'LoadAverage' => "$cpu",
    'Text' => "Load average: " . $cpu . "%",
]);

$cv->bye();
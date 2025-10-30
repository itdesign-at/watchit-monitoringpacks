#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;
use ITdesign\Plugins\Constants;
use ITdesign\Net\Snmp;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Plugins\CheckValue;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

#
# Include file for CheckPoint Monitoring Pack(s) for SNMP
#
#
# OIDs from: CP_SNMP_BestPracticesGuide.pdf
# The latest version of this document is at:
# http://supportcontent.checkpoint.com/documentation_download?ID=31396
#
# To learn more, visit the Check Point Support Center http://supportcenter.checkpoint.com.
#

$correlation = new StateCorrelation(['h' => $host, 's' => $service, 'Debug' => $debug]);
$snmp = new Snmp($host);

# abhaengig vom Service Namen werden unterschiedliche
#     snmp OIDs auf der firewall abgefragt
# powerSupplyStatus .1.3.6.1.4.1.2620.1.6.7.9.1.1.2
$powerSupplyStatus = $snmp->walk('.1.3.6.1.4.1.2620.1.6.7.9.1.1.2');
if (is_array($powerSupplyStatus)) {
    foreach ($powerSupplyStatus as $i => $status) {
        if ($status === 'Up') {
            $csv = true;
            $exit = Constants::OK;
        } else {
            $csv = false;
            $exit = Constants::CRITICAL;
        }
        $cv = new CheckValue([
            'k' => 'binary',
            'h' => $host,
            's' => "$service - PowerSupply $i",
            'Text' => "PowerSupply $i is '$status'",
            'State' => $exit,
            'Value'  => $csv,
            'Debug' => $debug
        ]);
        $correlation->add($cv);
    }
}

# Temperature sensors
$sensorName = $snmp->walk('.1.3.6.1.4.1.2620.1.6.7.8.1.1.2');
$sensorValue = $snmp->walk('.1.3.6.1.4.1.2620.1.6.7.8.1.1.3');
if (is_array($sensorName) && is_array($sensorValue) &&
    count($sensorName) === count($sensorValue)) {
    foreach ($sensorName as $i => $name) {
        $val = intval($sensorValue[$i]);
        $state = Constants::OK;
        if ($val > 54) {
            $state = Constants::CRITICAL;
        } else if ($val > 53) {
            $state = Constants::WARNING;
        }
        $cv = new CheckValue([
            'k' => 'gauge',
            'h' => $host,
            's' => "$service - Temperature Sensor $i",
            'State' => $state,
            'Text' => "Temperature '$name': {$val}° Celsius",
            'Value'  => $val,
            'Debug' => $debug
        ]);
        $correlation->add($cv);
    }
}

# FAN speed (RPM)
$sensorName = $snmp->walk('.1.3.6.1.4.1.2620.1.6.7.8.2.1.2');
$sensorValue = $snmp->walk('.1.3.6.1.4.1.2620.1.6.7.8.2.1.3');
if (is_array($sensorName) && is_array($sensorValue) &&
    count($sensorName) === count($sensorValue)) {
    foreach ($sensorName as $i => $name) {
        $val = intval($sensorValue[$i]);
        $cv = new CheckValue([
            'k' => 'gauge',
            'h' => $host,
            's' => "$service - Fan Sensor $i",
            'w' => "$val < 5000",
            'c' => "$val < 4000",
            'Text' => "Fan '$name': ${val} RPM",
            'Value'  => $val,
            'Debug' => $debug
        ]);
        $correlation->add($cv);
    }
}

$correlation->bye();

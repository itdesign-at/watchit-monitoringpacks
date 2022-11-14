#!/usr/bin/env php
<?php
#
# Query AKCP sensorProbe 2+, temperature via SNMP
#
# Shell example:
# # snmpwalk -On -v2c -c public akcprz1.demo.at .1.3.6.1.4.1.3854.3.5.2.1
# .1.3.6.1.4.1.3854.3.5.2.1.1.0.0.0.0.0 = STRING: "0.0.0.0.0"
# .1.3.6.1.4.1.3854.3.5.2.1.2.0.0.0.0.0 = STRING: "Temperature Port 1"
# .1.3.6.1.4.1.3854.3.5.2.1.3.0.0.0.0.0 = INTEGER: 3
# .1.3.6.1.4.1.3854.3.5.2.1.4.0.0.0.0.0 = INTEGER: 23
# .1.3.6.1.4.1.3854.3.5.2.1.5.0.0.0.0.0 = STRING: "C"
# .1.3.6.1.4.1.3854.3.5.2.1.6.0.0.0.0.0 = INTEGER: 2
# .1.3.6.1.4.1.3854.3.5.2.1.8.0.0.0.0.0 = INTEGER: 1
# etc.
# .1.3.6.1.4.1.3854.3.5.2.1.18.0.0.0.0.0 = INTEGER: 0
# .1.3.6.1.4.1.3854.3.5.2.1.19.0.0.0.0.0 = INTEGER: 0
# .1.3.6.1.4.1.3854.3.5.2.1.20.0.0.0.0.0 = INTEGER: 231  <--- raw temperature 23.1 degrees
#
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

const OID_Temperature = ".1.3.6.1.4.1.3854.3.5.2.1.20.0.0.0.0.0";
const OID_Description = ".1.3.6.1.4.1.3854.3.5.2.1.2.0.0.0.0.0";

# just to simulate integer and string
# const OID_Temperature = ".1.3.6.1.2.1.1.7.0";
# const OID_Description = ".1.3.6.1.2.1.1.5.0";

$keyword = $OPT['k'] ?? 'temperature'; 
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

$th = FilterThreshold::getThreshold(array('h' => $host, 'section' => 'temperature'));
$cv = new CheckValue([
        'k' => $keyword, 'h' => $host, 's' => "$service",
        'w' => $th['w'], 'c' => $th['c'],
	'Debug' => $debug
]);

$snmp = new Snmp($host);
$snmp->setDebug($debug);

$temperature = $snmp->get(OID_Temperature);
if (is_numeric($temperature)) {
	$temperature = floatval($temperature / 10);
	$sensorDescription = $snmp->get(OID_Description);
	$cv->add([
		'Value' => $temperature,
		'Text' => "$sensorDescription: $temperature °C",
	]);
} else {
	$cv->add([
		'State' => Constants::UNKNOWN,
		'Text' => 'no valid temperature data',
	]);
	if ($convertUnknown) {
		$cv->add(['State' => Constants::CRITICAL]);
	}
}
$cv->bye();

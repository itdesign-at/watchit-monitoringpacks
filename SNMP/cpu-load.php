#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$keyword = $OPT['k'] ?? 'cpu-load';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

if ($host === '') {
    print "host is empty or missing\n";
    exit(3);
}

$th = FilterThreshold::getThreshold(array('h' => $host, 'section' => 'cpu'));
$cv = new CheckValue([
        'k' => $keyword, 'h' => $host, 's' => "$service",
        'w' => $th['w'], 'c' => $th['c'],
        'Debug' => $debug]
);

$snmp = new Snmp($host);
$snmp->setDebug($debug);
$cpuLoad = $snmp->getCpuLoad("summary");
if (is_array($cpuLoad) && count($cpuLoad) > 0) {
    $cv->add($cpuLoad);
} else {
    $cv->add([Constants::Text => 'no SNMP data', Constants::State => Constants::UNKNOWN]);
}
$cv->bye();

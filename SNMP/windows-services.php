#!/usr/bin/env php
<?php

require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\Constants;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Utils\CommandLine;
use ITdesign\Net\Snmp;

const WindowsService = "Windows Service";

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$keyword = 'windowsServicesTable';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

$snmp = new Snmp($host);
$snmp->setDebug($debug);

$winServices = $snmp->getWindowsServices();

if (count($winServices) < 1) {
    print Constants::NoDataViaSNMP . "\n";
    exit(Constants::NUMERIC_UNKNOWN);
}

$customTable = new StateCorrelation(['k' => $keyword, 'h' => "$host", 's' => "$service", 'Debug' => $debug]);
$customTable->setHeader([WindowsService]);

foreach ($winServices as $s) {
    $customTable->add([WindowsService => $s]);
}

$customTable->set("Text", sprintf("%d Services", count($winServices)));
$customTable->bye();











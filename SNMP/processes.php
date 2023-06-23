#!/usr/bin/env php
<?php

require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\Constants;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Utils\CommandLine;
use ITdesign\Net\Snmp;

const Processes = "Processes";

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$keyword = 'processTable';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

$snmp = new Snmp($host);
$snmp->setDebug($debug);

$processes = $snmp->getProcesses();

if (count($processes) < 1) {
    print Constants::NoDataViaSNMP . "\n";
    exit(Constants::NUMERIC_UNKNOWN);
}

$customTable = new StateCorrelation(['k' => $keyword, 'h' => "$host", 's' => "$service", 'Debug' => $debug]);
$customTable->setHeader([Processes]);

foreach ($processes as $s) {
    $customTable->add([Processes => $s]);
}

$customTable->set("Text", sprintf("%d Processes", count($processes)));
$customTable->bye();
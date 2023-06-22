#!/usr/bin/env php
<?php

/**
 * Query Cisco IOS devices and read their cpu-usage
 *
 * http://www.oidview.com/mibs/9/CISCO-PROCESS-MIB.html
 *
 * Example threshold definition in the WATCHIT UI:
 * -----------------------------------------------
 * Name: cisco cpu-load Threshold
 * Section: CPU
 * Warning Threshold: @{LoadAverage} gt 80%
 * Critical Threshold: @{LoadAverage} gt 90%
 */

require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

// init CheckValue
$cv = new CheckValue([
      'k'      => 'gauge',
      'h'      => "$host",
      's'      => "$service",
      'Debug'  => $debug,
]);

# Cisco OIDs
$oid  = "1.3.6.1.4.1.9.9.109.1.1.1.1.8";
$snmp = new Snmp($host);

# Read cpu load from the net
$cpuLoad = $snmp->walk($oid);

// error handling if snmp does not respond or returns empty output
if ($cpuLoad == Snmp::$ERROR || empty($cpuLoad)) {
  if ($convertUnknown) {
      $cv->add([
        'Text'   => Constants::NoDataViaSNMP,
        'State'  => Constants::UNKNOWN,
      ]);
  } else {
      $cv->add([
        'Text'   => Constants::NoDataViaSNMP,
        'State'  => Constants::UNKNOWN,
      ]);
  }
}

# calc average for the whole result
$cpuCount = count($cpuLoad);
$cpuLoad  = array_sum($cpuLoad) / $cpuCount;

# Get threshold configuration
$th = FilterThreshold::getThreshold(array(
    'h'       => $host,
    'section' => "cpu",
    'Debug'   => "$debug"
));

$cv->add([
    'LoadAverage' => $cpuLoad,
    'w'           => $th['w'],
    'c'           => $th['c'],
    'Value'       => intval($cpuLoad),
    'Text'        => sprintf("%s CPUs, LoadAverage: %s%%", $cpuCount, $cpuLoad)
]);
$cv->bye();

#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");
  
use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
  
if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}
  
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
  
CommandLine::terminateOnEmpty($host);

$cv = new CheckValue([
  'k'    => 'gauge',
  'h'    => "$host",
  's'    => "$service",
  'Debug' => $debug,
]);

$snmp = new Snmp($host);
$tcpConnections = $snmp->get(".1.3.6.1.2.1.6.9.0");

if ($tcpConnections == Snmp::$ERROR) {
  $cv->add([
    'Text'   => Constants::NoDataViaSNMP,
    'State'  => Constants::UNKNOWN,
  ]);
  $cv->bye();
}

$cv->add([    
  'Value' => intval($tcpConnections),
  'Text'  => sprintf ("%d TCP connection(s)",$tcpConnections),
]);
        
$cv->bye();

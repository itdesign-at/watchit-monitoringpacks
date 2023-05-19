#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;
use ITdesign\Utils\FilterThreshold;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$keyword = $OPT['k'] ?? 'gauge';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

$cv = new CheckValue(['k' => "$keyword", 'h' => "$host", 's' => "$service", 'Debug' => $debug]);

$offset = 0;
try {
    $offset = (int)Common::getMonitoringPackParameter($OPT,'offset');
} catch (Exception $e) {
}

// Remote time via SNMP
$snmp = new Snmp("$host");
$remoteTimestamp = $snmp->getTime();
if ($remoteTimestamp === $snmp::$ERROR) {
    $cv->commit();
    $cv->setUnknown($OPT['convertUnknown'] ?? false,
        $OPT[Constants::UnknownText] ?? Constants::NoDataViaSNMP);
    $cv->bye();
}

// localtime
$myTime = new DateTime();
$localTimeStamp = $myTime->getTimestamp();

$timeDiffLocal = $localTimeStamp - $remoteTimestamp + $offset;

$th = FilterThreshold::getThreshold(array('h' => "$host", 'section' => 'time'));

$cv->add([
    'Text' => 'Difference: @{TimeDiffLocal} seconds (@{TimeReadable})',
    'Time' => date('U', $remoteTimestamp),
    'TimeReadable' => date("Y-m-d H:i:s", $remoteTimestamp),
    'TimeDiffLocal' => $timeDiffLocal,
    'TimeOffset' => $offset,
    'Value' => $timeDiffLocal,
    'w' => $th['w'],
    'c' => $th['c'],
]);

$cv->bye();









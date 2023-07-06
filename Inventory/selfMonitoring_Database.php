#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\MetricConfig;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

$cv = new CheckValue([
    'k' => 'gauge', 'h' => $host, 's' => "$service", 'Debug' => $debug
]);

$mc = new MetricConfig();
$base = $mc->getBase();
$apiKey=$base['ApiKey'];

$t0 = microtime(true);
exec (sprintf("curl -q -k https://localhost/api/inventory/hosts?apiKey=%s",$apiKey),$out,$exit);
$t1 = microtime(true);

if (!is_array($out) || $exit != 0) {
    $cv->add(['Text' => 'Unable to query DB via REST API','EXIT' => 2]);
    $cv->bye();
}

$back = json_decode($out[0], true);
if ($back === null) {
    $cv->add(['Text' => 'Unable to decode API answer','EXIT' => 2]);
    $cv->bye();
}

$text=sprintf ("DB query for %d hosts done in %.3f ms",count($back),1000*($t1-$t0));
$cv->add(['Text' => $text,'Value' => $t1-$t0]);
$cv->bye();
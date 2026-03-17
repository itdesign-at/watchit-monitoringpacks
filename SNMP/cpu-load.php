#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;
use ITdesign\Utils\Filters;
use ITdesign\Utils\Thresholds;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$keyword = $OPT['k'] ?? 'cpu-load';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

# read version to be backwards compatible
$v = @file_get_contents("/opt/watchit/etc/version");
$versionDate = ($v === false) ? 0 : intval(substr($v, strpos($v, '+') + 1, 8));

$cv = new CheckValue([
        'k' => $keyword, 'h' => $host, 's' => "$service",
        'Debug' => $debug
]);

$snmp = new Snmp($host);
$snmp->setDebug($debug);

$cpuLoad = $snmp->getCpuLoad("summary");

# fwrite (STDERR, print_r($cpuLoad, true));

# example data:
# array (
#    [Host] => watchit.itdesign.at
#    [Service] => CPU load
#    [Node] => watchit.itdesign.at
#    [LoadAverage] => 13
#    [Text] => 4 CPUs, Load average: 13.0%
#    [Value] => 13
# )


if (is_array($cpuLoad) && count($cpuLoad) > 0) {
    $cv->add($cpuLoad);
} else {
    $cv->setUnknown($OPT['convertUnknown'] ?? false,
        $OPT[Constants::UnknownText] ?? Constants::NoDataViaSNMP);
}

$cfg = ['h' => $host, 'section' => 'cpu', 'data' => $cpuLoad, 'Debug' => $debug];
if ($versionDate < 20260310) {
    $th = FilterThreshold::getThreshold($cfg);
    $cv->add(['w' => $th['w'], 'c' => $th['c']]);
} else {
    $exit = Thresholds::getExitState($cfg);
    $cv->add([Constants::Exit => $exit]);
}

$cv->bye();

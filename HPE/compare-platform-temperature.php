#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Utils\CommandLine;
use ITdesign\Net\Snmp;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$keyword = $OPT['k'] ?? '';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

// for development only; option -simulate
$simulate = $OPT['simulate'] ?? false;

CommandLine::terminateOnEmpty($host);

$oids = [
    "Temperature 1" => "1.3.6.1.4.1.25506.2.6.1.1.1.1.12.201",
    "Temperature 2" => "1.3.6.1.4.1.25506.2.6.1.1.1.1.12.202",
    "Temperature 3" => "1.3.6.1.4.1.25506.2.6.1.1.1.1.12.203",
    "Temperature 4" => "1.3.6.1.4.1.25506.2.6.1.1.1.1.12.204",
    "Temperature 5" => "1.3.6.1.4.1.25506.2.6.1.1.1.1.12.205",
    "Temperature 6" => "1.3.6.1.4.1.25506.2.6.1.1.1.1.12.206"
];

$corr = new StateCorrelation(['h' => "$host",'s' => "$service"]);

foreach ($oids as $service => $oid) {

    if ($simulate) {
        $temperature = random_int(50, 100);
    } else {
        $snmp = new Snmp($host);
        $snmp->setDebug($debug);
        $temperature = $snmp->get($oid);
    }

    switch ($service) {
        case "Temperature 1":
            $thWarning = "@{Value} gt 90";
            $thCritical = "@{Value} gt 100";
            break;
        case "Temperature 2":
            $thWarning = "@{Value} gt 90";
            $thCritical = "@{Value} gt 100";
            break;
        case "Temperature 3":
            $thWarning = "@{Value} gt 98";
            $thCritical = "@{Value} gt 108";
            break;
        case "Temperature 4":
            $thWarning = "@{Value} gt 98";
            $thCritical = "@{Value} gt 108";
            break;
        case "Temperature 5":
            $thWarning = "@{Value} gt 63";
            $thCritical = "@{Value} gt 70";
            break;
        case "Temperature 6":
            $thWarning = "@{Value} gt 110";
            $thCritical = "@{Value} gt 130";
            break;
    }

    $cv = new CheckValue(
        [
            'k' => 'gauge',
            'h' => $host,
            's' => $service,
            'w' => $thWarning,
            'c' => $thCritical,
            'Value' => $temperature,
            'Text' => "$service: $temperature C",
            'Debug' => $debug,
        ]
    );

    $cv->init();

    $corr->add($cv);
}

$corr->bye();



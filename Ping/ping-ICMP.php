#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;

const binary = "/opt/watchit/bin/pingParser";

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$keyword = $OPT['k'] ?? 'ping';
$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

$cmd = sprintf("%s -h '%s' -a '%s' 2>/dev/null", binary, $host, $address);

$out = [];
exec($cmd, $out, $exit);

if ($debug) {
    fwrite(STDERR, "$cmd\n");
    fwrite(STDERR, print_r($out,true));
}

if (!is_array($out) || count($out) !== 1) {
    printf("ERROR: unable to process %s\n", binary);
    if ($convertUnknown) {
        exit(Constants::NUMERIC_CRITICAL);
    }
    exit(Constants::NUMERIC_UNKNOWN);
}

// e.g. $out[0] = {"Host":"www.orf.at","Pl":0,"Rtt":0.000091,"Text":"0.09ms rtt, 0% packet loss"}
$data = json_decode($out[0], true);
if ($data === null) {
    printf("ERROR: unable to json_decode %s\n", $out[0]);
    if ($convertUnknown) {
        exit(Constants::NUMERIC_CRITICAL);
    }
    exit(Constants::NUMERIC_UNKNOWN);
}

$th = FilterThreshold::getThreshold(['h' => $host, 'section' => 'ping']);

// use CheckValue to compare against warning and critical
$cv = new CheckValue([
    'k' => $keyword,
    'h' => "$host",
    's' => "$service",
    'w' => $th['w'],
    'c' => $th['c']
]);

if (array_key_exists('Rtt', $data) && array_key_exists('Pl', $data)) {
    $cv->add([
        'Text' => $data['Text'],
        'Rtt' => $data['Rtt'],
        'Pl' => $data['Pl'],
        // add those three values for backwards compatibility when comparing
        // warning and critical threshold $th['w'] and $th['c']
        'RoundTripTime' => $data['Rtt'],
        'RoundTripTime.ms' => $data['Rtt'] * 1000,
        'PacketLoss' => $data['Pl']
    ]);
} else if (!array_key_exists('Rtt', $data) && array_key_exists('Pl', $data)) {
    /**
     * $data = Array
     * (
     * [Host] => www.demo.at
     * [Pl] => 100
     * [Text] => 100% packet loss
     * )
     */
    $cv->add([
        'Text' => $data['Text'],
        'Pl' => $data['Pl'],
        'Rtt' => -1, // the ODIN service expects both 'Rtt' and 'Pl' to write a valid record
        'RoundTripTime' => -1,
        'RoundTripTime.ms' => -1,
        'PacketLoss' => 100,
    ]);
    // CRITICAL makes sense when no thresholds are set
    // could be overwritten in the thresholds table ...
    if ($th['w'] == "" && $th['c'] == "") {
        $cv->add([Constants::State => Constants::CRITICAL]);
    }
} else {
    // this code segment should never be reached
    $cv->add($data);
    if ($convertUnknown) {
        $cv->add([Constants::State => Constants::CRITICAL]);
    }
    if (isset($OPT[Constants::UnknownText])) {
        $data[Constants::Text] = $OPT[Constants::UnknownText];
    }
}

$cv->bye();

#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;

const binary = "/opt/watchit/plugins/itd_check_ping.pl";

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$keyword = $OPT['k'] ?? 'ping';
$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

if ($address === '') {
    $address = $host;
}

CommandLine::terminateOnEmpty($address);

// $cmd is used to get ping data only - do not give -k here to avoid sending
// measurement data to NATS, used to get $data['Text'], $data['Rtt'] and $data['Pl']
$cmd = sprintf("%s -h '%s' -s '%s' -a '%s' -oF json", binary, $host, $service, $address);

if ($debug) {
    $cmd .= " -Debug";
    CheckValue::dbg("main", "cmd", $cmd);
}

$data = [];

exec($cmd, $out, $exit);
if (count($out) !== 1) {
    exit(Constants::NUMERIC_CRITICAL);
}
$data = json_decode($out[0], true);

$th = FilterThreshold::getThreshold(array('h' => $host, 'section' => 'ping'));

// use CheckValue to compare against warning and critical
$cv = new CheckValue(array_merge($OPT,['k' => $keyword, 'w' => $th['w'], 'c' => $th['c']]));

if (array_key_exists('Rtt', $data) && array_key_exists('Pl', $data)) {
    $cv->add([
        'Text' => $data['Text'],
        'Rtt' => $data['Rtt'],
        'Pl' => $data['Pl'],
        'RoundTripTime' => $data['Rtt'],
        'RoundTripTime.ms' => $data['Rtt'] * 1000,
        'PacketLoss' => $data['Pl']
    ]);
} else {
    $cv->add([
        'Text' => 'No answer - host is down or unreachable',
        'Rtt' => -1,
        'Pl' => 100,
        'RoundTripTime' => -1,
        'RoundTripTime.ms' => -1,
        'PacketLoss' => 100
    ]);
    if ($convertUnknown) {
        $cv->add([Constants::State => Constants::CRITICAL]);
    }
    if (isset($OPT[Constants::UnknownText])) {
        $data[Constants::Text] = $OPT[Constants::UnknownText];
    }
}

$cv->bye();

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

CommandLine::checkEmptyHost($address);

$cmd = sprintf("%s -k %s -h '%s' -s '%s' -a '%s' -oF json",
    binary, $keyword, $host, $service, $address
);

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

// use CheckValue to compare against warning and critical
$th = FilterThreshold::getThreshold(array('h' => $host, 'section' => 'ping'));
$cv = new CheckValue(['w' => $th['w'], 'c' => $th['c'], 'Debug' => $debug]);
if (array_key_exists('Rtt', $data) && array_key_exists('Pl', $data)) {
    $cv->add([
        'RoundTripTime' => $data['Rtt'],
        'RoundTripTime.ms' => $data['Rtt'] * 1000,
        'PacketLoss' => $data['Pl']
    ]);
} else {
    $cv->add($data);
    if ($convertUnknown) {
        $cv->add([Constants::State => Constants::CRITICAL]);
    }
    if (isset($OPT[Constants::UnknownText])) {
        $data[Constants::Text] = $OPT[Constants::UnknownText];
    }
}

$cv->init();

if (array_key_exists(Constants::DSN, $data) && array_key_exists(Constants::Text, $data)) {
    $out = [Constants::DSN => $data[Constants::DSN], Constants::Text => $data[Constants::Text]];
    print json_encode($out);
} else if (array_key_exists(Constants::Text, $data)) {
    print $data[Constants::Text];
} else {
    print "no data";
}
print "\n";

exit($cv->getExit());



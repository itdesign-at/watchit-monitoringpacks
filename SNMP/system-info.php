#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Utils\CommandLine;

const binary = "/opt/watchit/bin/osDetection";

// allow to call the script directly without state_correlation.php by
// checking $OPT
if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

// option -h is a must -> reference to hosts-exported.json
$host = $OPT['h'] ?? '';
if ($host === '') {
    print "host is empty or missing\n";
    exit(3);
}
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

// e.g. /opt/watchit/bin/osDetection -h dev-dc-01 -oF json
// {"description":"Hardware: Intel64 Family 6 Model ...","deviceTypeId":"zBBeAFKt",
// "operatingSystemName":"Windows Server 2012 R2","uptime":15708721,"vendorId"","vendorName":"Microsoft"}
exec(binary . " -h \"$host\" -oF json", $out, $exit);
if (count($out) != 1) {
    print ("unable to get data via SNMP");
    exit(3);
}
$data = json_decode($out[0], true);
if ($data === null) {
    print ("unable to json_decode output");
    exit(3);
}

// correlation table init
$correlation = new StateCorrelation([
    'k' => StateCorrelation::MetricType,
    'h' => $host,
    's' => $service,
    'Debug' => $debug]);

// convert json keys to nice names
$textMapper = [
    'vendorName' => 'Vendor',
    'operatingSystemName' => 'OS',
    'description' => 'Description'
];

foreach (['vendorName', 'operatingSystemName', 'description'] as $key) {
    if (!array_key_exists($key, $data)) {
        continue;
    }
    $value = $data[$key];
    if ($service === '') {
        $cv = new CheckValue([
            'h' => $host, 'Value' => "$value", 'Debug' => $debug,
            'Text' => sprintf('%s: %s', $textMapper[$key], $value),
        ]);
    } else {
        $cv = new CheckValue([
            'h' => $host, 's' => "$service - $key", 'Value' => "$value", 'Debug' => $debug,
            'Text' => sprintf('%s: %s', $textMapper[$key], $value),
        ]);
    }

    $correlation->add($cv);
}

if (array_key_exists("uptime", $data)) {
    if ($service === '') {
        $cv = new CheckValue([
            'k' => 'counter', 'h' => $host,
            'Value' => $data['uptime'], 'Debug' => $debug]);
    } else {
        $cv = new CheckValue([
            'k' => 'counter', 'h' => $host, 's' => "$service - uptime",
            'Value' => $data['uptime'], 'Debug' => $debug]);
    }

    $correlation->add($cv);
}
$correlation->bye();

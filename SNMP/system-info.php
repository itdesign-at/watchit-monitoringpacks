#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;

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
    'k' => Constants::MetricCorrelation,
    'h' => $host, 's' => $service,
    'Debug' => $debug]);

// convert json keys to nice names
$textMapper = [
    'vendorName' => 'Vendor',
    'operatingSystemName' => 'Operating System',
    'description' => 'Description'
];

foreach (['vendorName', 'operatingSystemName', 'description'] as $key) {
    if (!array_key_exists($key, $data)) {
        continue;
    }

    $svc = $textMapper[$key];
    $val = $data[$key];

    if (str_contains($val, 'UNKNOWN')) {
        continue;
    }

    // do not give 'k' as parameter -> avoids writing long term data
    $cv = new CheckValue(['h' => $host, 's' => $svc, 'Text' => $val, 'Debug' => $debug]);
    $correlation->add($cv);
    $correlation->arrayAppend(Constants::Text, $cv->getText());

    // do not write "Description" when "Operating System" is already printed
    // it is more readable for the customer
    if ($key == 'operatingSystemName') {
	    break;
    }
}

if (array_key_exists("uptime", $data)) {
    $uptime = $data['uptime']; // just a shortcut
    $cv = new CheckValue([
        'k' => Constants::MetricCounter, 'h' => $host, 's' => "SNMP Uptime",
        'Text' => "SNMP Uptime: " . Common::seconds2Readable($uptime),
        'Value' => $uptime, 'Debug' => $debug]);
    $correlation->add($cv);
    $correlation->arrayAppend(Constants::Text, $cv->getText());
}

$correlation->commit();
print ($correlation->args['Output'] . "\n");
exit(0);


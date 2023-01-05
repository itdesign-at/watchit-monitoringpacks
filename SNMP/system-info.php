#!/usr/bin/env php
<?php
/**
 *
 * Read sysDescr from OID .1.3.6.1.2.1.1.1.0 and sysUptime with the
 * osDetection binary and compares the result against
 * /opt/watchit/var/etc/os-detection.json. The "oid" parameter from
 * osDetection allows to specify an alternative oid to use.
 *
 * SNMP config params are taken from hosts-exported.json.
 *
 */
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;

const binary = "/opt/watchit/bin/osDetection";

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

// option -h is a must -> reference to hosts-exported.json
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

// init only
$cvUptime = new CheckValue([
    'k' => Constants::MetricCounter, 'h' => $host, 's' => "Uptime", 'Debug' => $debug,
]);

// e.g. /opt/watchit/bin/osDetection -h dev-dc-01 -oid .1.3.6.1.2.1.1.1.0  -oF json
exec(binary . " -h \"$host\" -oid .1.3.6.1.2.1.1.1.0 -oF json", $out, $exit);
if (count($out) != 1) {
    // missing "Value" -> writes "NoData" in the backend
    $cvUptime->commit();
    $cvUptime->setUnknown($OPT['convertUnknown'] ?? false,
        $OPT[Constants::UnknownText] ?? Constants::NoDataViaSNMP);
    $cvUptime->bye();
}

$data = json_decode($out[0], true);
if ($data === null) {
    print ("unable to json_decode output\n");
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

    // store days instead of seconds - makes more sense
    $days = intval($uptime/86400);

    $cvUptime->add(['Text' => "Uptime: " . Common::seconds2Readable($uptime), 'Value' => $days]);

    $correlation->add($cvUptime);
    $correlation->arrayAppend(Constants::Text, $cvUptime->getText());
}

$correlation->init();    // calc exit only
$correlation->commit();  // send all measurements

// get line(s) or a single json line
$out = $correlation->getOutput();
if (is_array($out)) {
    print implode(", ", $out);
} else {
    print $out;
}
print ("\n");
exit($correlation->getExit());

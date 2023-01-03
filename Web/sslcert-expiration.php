#!/usr/bin/env php
<?php

require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;

$binary = "/opt/watchit/bin/sslCertInfo";

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$convertUnknown = $OPT['convertUnknown'] ?? false;
$debug = $OPT['Debug'] ?? false;

if (!is_executable($binary)) {
    bye("$binary not found", $convertUnknown);
}
$errorState = Constants::UNKNOWN;
if ($convertUnknown) {
    $errorState = Constants::CRITICAL;
}

$cv = new CheckValue(['k' => 'gauge', 'h' => $host, 's' => $service, 'Debug' => $debug]);

// the external binary must return a valid json output
$cmd = sprintf("%s -h '%s' -i '%s' -out json", $binary, $host, $address);
exec($cmd, $out, $exit);
$data = json_decode($out[0], true);

if ($exit != 0 || !is_array($data)) {
    $cv->add([
        'Text' => 'Unable to read SSL certificate information',
        'State' => $errorState
    ]);
    $cv->bye();
}

// e.g. $expireInSeconds = 9409142
$expireInSeconds = $data["SSL certificate expiration"] ?? -1;

// e.g. $to = "2023-04-22 12:03:50 +0000 UTC"
$to = $data["SSL certificate validity"]["To"] ?? "";

if ($expireInSeconds == -1 || $to == "") {
    $cv->add([
        'Text' => "Invalid data returned from $cmd",
        'State' => $errorState
    ]);
    $cv->bye();
}

$criticalExpiration = Common::getMonitoringPackParameter(array_merge($OPT, array(
    'key' => 'Critical', 'default' => '10d'
)));

// 10 days is default
$threshold = 10 * 86400;
if (str_ends_with($criticalExpiration, "d")) {
    $threshold = 86400 * (str_replace("d", "", $criticalExpiration));
}

$state = Constants::OK;
if ($expireInSeconds < $threshold) {
    $state = Constants::CRITICAL;
}

$cv->add([
    'Text' => "Certificate expiration date: $to",
    'Value' => $expireInSeconds,
    'State' => $state,
]);
$cv->bye();

/**
 * @param string $output
 * @param bool $convertUnknown
 * @return void
 */
function bye(string $output, bool $convertUnknown)
{
    print "$output\n";
    if ($convertUnknown) {
        exit(Constants::NUMERIC_CRITICAL);
    } else {
        exit(Constants::NUMERIC_UNKNOWN);
    }
}



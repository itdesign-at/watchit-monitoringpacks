#!/usr/bin/env php
<?php
/**
 * Check SSL/TLS certificate for a dedicated host with the
 * external program 'sslCertInfo'. Stores expiration days
 * in the long term data store.
 *
 * Defaults:
 *  TCP Port = 443
 *  Warning threshold in days: 60
 *  Critical threshold in days: 30
 *
 * 2023-01-03 first version, WN
 * This version has no monitoring pack parameters; can be added later...
 *
 */

require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;

// external binary to call
$binary = "/opt/watchit/bin/sslCertInfo";

// default = SSL/TLS
$tcpPort = 443;

// days of cert expiration
$warning = 60;
$critical = 30;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$convertUnknown = $OPT['convertUnknown'] ?? false;
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

if (!is_executable($binary)) {
    bye("$binary not found", $convertUnknown);
}

$errorState = Constants::UNKNOWN;
if ($convertUnknown) {
    $errorState = Constants::CRITICAL;
}

// init a new measurement object
$cv = new CheckValue(['k' => 'gauge', 'h' => $host, 's' => $service, 'Debug' => $debug]);

// build external command with or without IP address
if ($host == $address || $address == "") {
    $cmd = sprintf("%s -h '%s' -p %s -out json", $binary, $host, $tcpPort);
} else {
    $cmd = sprintf("%s -h '%s' -a '%s' -p %s -out json", $binary, $host, $address, $tcpPort);
}


exec($cmd, $out, $exit);
$data = json_decode($out[0], true);

if ($debug) {
    fwrite(STDERR, "\n$cmd\n\n");
    fwrite(STDERR, print_r($data, true));
}

if ($exit != 0 || !is_array($data)) {
    $cv->add([
        'Text' => 'Unable to get certificate information',
        'State' => $errorState
    ]);
    $cv->bye();
}

/**
 * $data (as json) example:
 * {
 *  "Connection to": "8.8.8.8:443",
 *  etc.
 * },
 *  "SSL certificate": {
 *      "Expiration": 3977465,
 *      "Expiration days": 46,
 *      "From": "2022-11-28 08:19:04 +0000 UTC",
 *      "To": "2023-02-20 08:19:03 +0000 UTC"
 *  },
 *  "Server key": {
 *      "CN": "dns.google"
 *  }
 * }
 **/

if (array_key_exists('SSL certificate', $data)) {
    $days = $data['SSL certificate']['Expiration days'];
    $expirationDate = $data['SSL certificate']['To'];
} else {
    $cv->add([
        'Text' => 'Unable to interpret json content',
        'State' => $errorState
    ]);
    $cv->bye();
}

// get the CN (for output only)
$cn = '';
if (array_key_exists('Server key', $data)) {
    $sk = $data['Server key'];
    if (array_key_exists('CN', $sk)) {
        $cn = $sk['CN'];
    }
}

$state = Constants::OK;
if ($days < $warning) {
    $state = Constants::WARNING;
}
if ($days < $critical) {
    $state = Constants::CRITICAL;
}

if ($cn == '') {
    if ($state == Constants::OK) {
        $text = "Certificate expires in $days day(s)";
    } else {
        $text = "Certificate expires on $expirationDate";
    }
} else {
    if ($state == Constants::OK) {
        $text = "Certificate '$cn' expires in $days day(s)";
    } else {
        $text = "Certificate '$cn' expires on $expirationDate";
    }
}

$cv->add(['Text' => "$text", 'Value' => $days, 'State' => $state,]);
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



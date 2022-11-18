#!/usr/bin/env php
<?php
#
# Round trip measurement by sending the current time via
# NATS & ODIN to the backend. Retrieve it with REST API.
#
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$now = date('Y-m-y h:i:s');

$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? 'NATS and ODIN round trip check';
$debug = $OPT['Debug'] ?? false;

CommandLine::checkEmptyHost($host);

// store $now on the message broker defined in baseConfig.json
$shellCommand = sprintf(
    "/opt/watchit/bin/publish -out requestUrl -p V1.file -h '%s' -s '%s' '%s'", $host, $service, $now);
if ($debug) {
    $shellCommand .= " -Debug";
    fwrite(STDERR, $shellCommand . "\n");
}
$fromApi = [];
exec($shellCommand, $fromApi, $exit);

// e.g. $out[0] = https://localhost.itdesign.at:7849/V1/file/node/localhost/Message%20Broker?apiKey=AuFNteXq
if (count($fromApi) == 1) {
    $url = $fromApi[0];
} else {
    print "NATS publishing failed ($shellCommand)\n";
    exit(Constants::NUMERIC_CRITICAL);
}
if ($debug) {
    fwrite(STDERR, "curl -k '$url'" . "\n");
}

// Get the file back via REST API
$ch = curl_init();
if ($ch === false) {
    print "Unknown to init php curl_init()\n";
    exit(Constants::NUMERIC_CRITICAL);
}
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$fromApi = curl_exec($ch);
if ($fromApi === false) {
    print "No content from $url\n";
    exit(Constants::NUMERIC_CRITICAL);
}
curl_close($ch);

if ($now === $fromApi) {
    print "NATS and ODIN service are OK\n";
    exit(Constants::NUMERIC_OK);
}

if ($debug) {
    var_dump($fromApi);
}

print "NATS or ODIN service error\n";
exit(Constants::NUMERIC_CRITICAL);

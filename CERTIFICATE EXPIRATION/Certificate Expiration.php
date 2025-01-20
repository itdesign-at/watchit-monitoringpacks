#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Utils\Common;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

$url = Common::getMonitoringPackParameter($OPT,'url');


$cv = new CheckValue([
    'k'    => 'binary',
    'h'    =>  $host,
    's'    =>  $service,
    'Debug' => $debug,
]);

$result = getSslCertificateViaCurl($url);

$endDate = $result['endDate'];
$daysLeft = getDaysUntilExpiration($endDate);

$state = Constants::OK;
$exit = 0;
$text = "Certificate expires in $daysLeft days, on $endDate";

if($daysLeft < 30 ) {
  $state = Constants::WARNING;
  $exit = 1;
} 
if ($daysLeft < 10) {
  $state = Constants::CRITICAL;
  $exit = 2;
}

$cv->add([
  'State' => $state,
  'Exit' => $exit,
  'Text' =>  $text,
  'Value' => $exit === 0 ? true : false,
]);

$cv->bye();

function getSslCertificateViaCurl($url) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CERTINFO, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    curl_exec($ch);
    
    $certInfo = curl_getinfo($ch, CURLINFO_CERTINFO);
    if (!$certInfo) {
        return ["error" => "Failed to retrieve certificate information."];
    }
    
    // Parse start and expiration dates
    $cert = openssl_x509_parse($certInfo[0]['Cert']);
    if (!$cert) {
        return ["error" => "Failed to parse certificate data."];
    }

    return [
        "startDate" => date('Y-m-d H:i:s', $cert['validFrom_time_t']),
        "endDate" => date('Y-m-d H:i:s', $cert['validTo_time_t']),
    ];
}

function getDaysUntilExpiration($endDate) {
    $currentDate = new DateTime();
    $expirationDate = new DateTime($endDate);
    $interval = $currentDate->diff($expirationDate);
    return $interval->days;
}

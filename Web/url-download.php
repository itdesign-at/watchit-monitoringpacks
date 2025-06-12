#!/usr/bin/env php
<?php
/**
 * Download and check web content via PHP curl.
 * Ported 2023-01-02 from the SLES url download package.
 *
 * Pack parameters:
 * ----------------
 * Download_URL     string (defaults to https://<host>)
 * Warning_Time     float in seconds (defaults to 3 seconds)
 * Critical_Time    float in seconds (defaults to 10 seconds)
 * Download_Content string (no default)
 *
 */

require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;

/*
 * This code allows to print the required json config to STDOUT. The string 
 * can than be used as package parameter 'JsonConfig' for configuring the 
 * package. It is an optional; each of the pack parameters can be set individually, too.
 *
 * Command line params:
 * -J ... force JsonConfig to be displayed to STDOUT
 * -u ... URL
 * -w ... warning time in seconds
 * -c ... critical time in seconds
 * -C ... content string
 * -username ... username
 * -password ... Password
 *
 * Examples from the shell:
 * ------------------------
 * php /cfg/packages/URL\ download.php -J -u http://www.orf.at -w 3 -c 5 
 * {"Download_URL":"http:\/\/www.orf.at","Warning_Time":"3","Critical_Time":"5"}
 *
 * php /cfg/packages/URL\ download.php -J -u http://www.orf.at -w 3 -c 5 -C 'Nachrichten'
 * {"Download_URL":"http:\/\/www.orf.at","Warning_Time":"3","Critical_Time":"5","Download_Content":"Nachrichten"}
 */
if (in_array('-J', $argv)) {
    $opt = getopt('u:w:c:m:C:');
    $jsonConfig = array();
    foreach ($opt as $key => $value) {
        switch ($key) {
            case 'u':
                $jsonConfig['Download_URL'] = $value;
                break;
            case 'w':
                $jsonConfig['Warning_Time'] = $value;
                break;
            case 'c':
                $jsonConfig['Critical_Time'] = $value;
                break;
            case 'C':
                $jsonConfig['Download_Content'] = $value;
                break;
            case 'username':
                $jsonConfig['Username'] = $value;
                break;
            case 'password':
                $jsonConfig['Password'] = $value;
                break;
        }
    }
    print (json_encode($jsonConfig)) . PHP_EOL;
    exit (0);
};

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;



// WN: convert UNKNOWN ist nicht implementiert; sobald der Download schief geht ist
// es immer CRITICAL!
// $convertUnknown = $OPT['convertUnknown'] ?? false;

try {
  // [* CodeParam JsonConfig ; Value={"Download_URL":"http://www.itdesign.at","Warning_Time":"5","Critical_Time":"OFF","Download_Method":"curl"} ; Desc=JSON config for this package *]
  $jsonConfig = Common::getMonitoringPackParameter($OPT,'JsonConfig');
} catch (Exception $e) {
  $jsonConfig = '';
}

$config = array();
if ($jsonConfig !== '') {
    $config = (array)json_decode($jsonConfig);
}

if (!array_key_exists('Download_URL', $config)) {
    try {
        // [* CodeParam Download_URL ; Value=www.itdesign.at ; Desc=URL to download *]
        $config['Download_URL'] = Common::getMonitoringPackParameter($OPT,'Download_URL');
    } catch (Exception $e) {
      $config['Download_URL'] = "https://$host";
    }
}

if (!array_key_exists('Download_Content', $config)) {
    try {
      // [* CodeParam Download_Content ; Value=Welcome ; Desc=Content to search for on the website *]
      $config['Download_Content'] = Common::getMonitoringPackParameter($OPT,'Download_Content');
    } catch (Exception $e) {
      $config['Download_Content'] = '';
    }
}

if (!array_key_exists('Warning_Time', $config)) {
    try {
      // [* CodeParam Warning_Time ; Value=3 ; Desc=Warning time in seconds *]
      $config['Warning_Time'] = Common::getMonitoringPackParameter($OPT,'Warning_Time');
    } catch (Exception $e) {
      $config['Warning_Time'] = '3';
    }
}

if (!array_key_exists('Critical_Time', $config)) {
    try {
      // [* CodeParam Critical_Time ; Value=10 ; Desc=Critical time in seconds *]
      $config['Critical_Time'] = Common::getMonitoringPackParameter($OPT,'Critical_Time');
    } catch (Exception $e) {
      $config['Critical_Time'] = '10';
    }
}

if (!array_key_exists('Username', $config)) {
    try {
        // [* CodeParam Username ; Value=username ; Desc=Username for Basic Authentication *]
        $config['Username'] = Common::getMonitoringPackParameter($OPT,'Username');
    } catch (Exception $e) {
        $config['Username'] = '';
    }
}

if (!array_key_exists('Password', $config)) {
    try {
        // [* CodeParam Password ; Value=password ; Desc=Password for Basic Authentication *]
        $config['Password'] = Common::getMonitoringPackParameter($OPT,'Password');
    } catch (Exception $e) {
        $config['Password'] = '';
    }
}


// optional content string to search for
$contentToCheck = $config['Download_Content'];

// service definitions for CheckValue
$serviceDownload = $config['Download_URL'] . ' download';
$serviceDownloadTime = $config['Download_URL'] . ' downloadtime';
$serviceContent = "Check content string '$contentToCheck'";

// do the download now
$t0 = microtime(TRUE);

$ch = curl_init();
setCurlParameter($ch, $config);
$out = curl_exec($ch);
$exit = curl_errno($ch);

$t1 = microtime(TRUE);

if ($exit == 0) {
    $downloadStateText = 'OK';
    $downloadState = Constants::OK;
} else {
    $downloadStateText = 'failed';
    $downloadState = Constants::CRITICAL;
}

$downloadTime = sprintf('%.6f', $t1 - $t0);

$cvDownload = new CheckValue(array(
    'k' => 'binary', 'h' => $host,
    's' => $serviceDownload,
    'Text' => "Download $downloadStateText",
    'Value' => $downloadState == Constants::OK,
    'State' => $downloadState,
    'Debug' => $debug,
));


// it does not make sense to continue when download fails
if ($downloadState != Constants::OK) {
    // force "NoData" to the download time statistic
    $cvDownloadTime = new CheckValue(array(
        'k' => 'gauge', 'h' => $host, 's' => $serviceDownloadTime,
        'Text' => "Download failed", 'State' => Constants::CRITICAL,
        'Debug' => $debug,
    ));
    $cvDownloadTime->init()->commit();

    // force "NoData" to optional content check
    if ($contentToCheck != "") {
        $cvContent = new CheckValue(array(
            'k' => 'binary', 'h' => $host, 's' => $serviceContent,
            'Text' => "Download failed", 'State' => Constants::CRITICAL,
            'Debug' => $debug,
        ));
        $cvContent->init()->commit();
    }

    $cvDownload->bye();
}

// correlation table init
$correlation = new StateCorrelation(['h' => "$host", 's' => "$service", 'Debug' => $debug]);

$cvDownload->init()->commit();
$correlation->add($cvDownload);

# ************ download time  ************
$cvConfig = [
      'k' => 'gauge', 
      'h' => $host, 
      's' => $serviceDownloadTime,
      'Text' => sprintf("Downloaded in %.2f ms", 1000 * $downloadTime),
      'Value' => $downloadTime,
      'Debug' => $debug
];

// check if warning oder critical time is set to OFF 
if ($config['Warning_Time'] != 'OFF' && $config['Critical_Time'] != 'OFF') {
    $cvConfig[] = array(
        'w'     => "$downloadTime > " . $config['Warning_Time'],
        'c'     => "$downloadTime > " . $config['Critical_Time']
    );
}
if ($config['Warning_Time'] === 'OFF') {
    $cvConfig[] = array(
        'w'     => 'OFF',
        'c'     => "$downloadTime > " . $config['Critical_Time']
    );
}
if ($config['Critical_Time'] === 'OFF') {
    $cvConfig[] = array(
        'w'     => "$downloadTime > " . $config['Warning_Time'],
        'c'     => 'OFF'
    );
}

$cvDownloadTime = new CheckValue($cvConfig);

$cvDownloadTime->init()->commit();
$correlation->add($cvDownloadTime);

// test if content should be checked, too - otherwise we are finished

if ($contentToCheck === '') {
    $correlation->bye();
}

# ************ check WEB Content  ************
$counter = substr_count($out, $config['Download_Content']);

$cvContent = new CheckValue(array(
    'k' => 'binary', 'h' => $host, 's' => $serviceContent,
    'OkText' => sprintf("String '%s' found %dx", $contentToCheck, $counter),
    'CriticalText' => "String '${contentToCheck}' not found",
    'w' => 'OFF',
    'c' => "$counter < 1",
    'Value' => $counter > 0,
    'Debug' => $debug,
));

$cvContent->init()->commit();
$correlation->add($cvContent);

$correlation->bye();

function setCurlParameter($ch, $config)
{
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (WindowsNT 6.3; WOW64; rv:36.0) Gecko/20100101 Firefox/36.0");
    # returns the curl_exec-Command as string instead of printing to the screen
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    # needs to be set to 'false' to overcome Certificate Errors
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    # set following redirections -> maximum are 3 redirections
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    # setting url
    curl_setopt($ch, CURLOPT_URL, $config['Download_URL']);
    # set Username and Password for Basic Authentication
    if (!empty($config['Username']) && !empty($config['Password'])) {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_USERPWD, "$config[Username]:$config[Password]");
    }
    return $ch;
}

#!/usr/bin/env php
<?php
/**
 * Download and check web content via PHP curl.
 * Ported 2023-01-02 from the SLES url download package.
 */

require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;

/*
 * Attention!
 * ----------
 * This code allows to print the required json config to STDOUT. The string 
 * can than be used as package parameter 'JsonConfig' for configuring the 
 * package.
 *
 * Command line params:
 * -J ... force JsonConfig to be displayed to STDOUT
 * -u ... URL
 * -w ... warning time in seconds
 * -c ... critical time in seconds
 * -C ... content string
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
        }
    }
    print (json_encode($jsonConfig)) . PHP_EOL;
    exit (0);
};

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
if ($host === '') {
    print "host is empty or missing\n";
    exit(Constants::NUMERIC_UNKNOWN);
}

$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

/* all parameters can be configured in a json string */
$jsonConfig = Common::getMonitoringPackParameter(array_merge($OPT, array(
    'key' => 'JsonConfig', 'default' => ''
)));

$config = array();
if ($jsonConfig !== '') {
    $config = (array)json_decode($jsonConfig);
}

if (!array_key_exists('Download_URL', $config)) {
    $config['Download_URL'] = Common::getMonitoringPackParameter(
        array_merge($OPT, array(
            'key' => 'Download_URL', 'default' => 'https://@{h}'
        )));
}

if (!array_key_exists('Download_Content', $config)) {
    $config['Download_Content'] = Common::getMonitoringPackParameter(
        array_merge($OPT, array(
            'key' => 'Download_Content', 'default' => ''
        )));
}

if (!array_key_exists('Warning_Time', $config)) {
    $config['Warning_Time'] = Common::getMonitoringPackParameter(
        array_merge($OPT, array(
            'key' => 'Warning_Time', 'default' => '3'
        )));
}

if (!array_key_exists('Critical_Time', $config)) {
    $config['Critical_Time'] = Common::getMonitoringPackParameter(
        array_merge($OPT, array(
            'key' => 'Critical_Time', 'default' => '10'
        )));
}

$correlation = new StateCorrelation(['h' => "$host", 's' => "$service", 'Debug' => $debug]);

$t0 = microtime(TRUE);

$ch = curl_init();
setCurlParameter($ch, $config['Download_URL']);
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

$checkValue = new CheckValue(array(
    'k' => 'binary', 'h' => $host,
    's' => $config['Download_URL'] . ' download',
    'Text' => "Download $downloadStateText",
    'Value' => $downloadState == Constants::OK,
    'State' => $downloadState,
    'Debug' => $debug,
));

// it does not make sense to continue when download fails
if ($downloadState != Constants::OK) {
    $checkValue->bye();
}

$checkValue->init();
$correlation->add($checkValue->getData());

# ************ download time  ************
$checkValue = new CheckValue(array(
    'k' => 'gauge', 'h' => $host,
    's' => $config['Download_URL'] . ' downloadtime',
    'Text' => sprintf("Downloaded in %.2f ms", 1000 * $downloadTime),
    'w' => "$downloadTime > " . $config['Warning_Time'],
    'c' => "$downloadTime > " . $config['Critical_Time'],
    'Value' => $downloadTime,
    'Debug' => $debug,
));

$checkValue->init();
$correlation->add($checkValue->getData());

// test if content should be checked, too - otherwise we are finished
$content = $config['Download_Content'];
if ($content === '') {
    $correlation->bye();
}

# ************ check WEB Content  ************
$counter = substr_count($out, $config['Download_Content']);

$checkValue = new CheckValue(array(
    'k' => 'binary', 'h' => $host,
    's' => "Check content string '$content'",
    'OkText' => sprintf("String '%s' found %dx", $content, $counter),
    'CriticalText' => "String '${content}' not found",
    'w' => 'OFF',
    'c' => "$counter < 1",
    'Value' => $counter > 0,
    'Debug' => $debug,
));

$checkValue->init();
$correlation->add($checkValue->getData());

$correlation->bye();

function setCurlParameter($ch, $sUrl)
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
    curl_setopt($ch, CURLOPT_URL, $sUrl);
    return $ch;
}

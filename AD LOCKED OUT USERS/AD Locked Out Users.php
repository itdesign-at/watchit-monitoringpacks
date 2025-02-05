#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");
  
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;
use ITdesign\Utils\Common;
  
if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}
  
$keyword = $OPT['k'] ?? '';
$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;
  
CommandLine::terminateOnEmpty($host);

$cmd = "ssh bitviseserverhiereinfügen 'powershell C:\\ITdesign\\WatchIT\\powershell\\Get-LockedOutUsers.ps1'";

exec($cmd, $out, $exit);

if ($exit > 0) {
    print "An Error occurred while executing the PowerShell Script";
    exit(3);
}

$jsonString = implode("", $out);
$users = json_decode($jsonString, true);

$correlation = new StateCorrelation(['h' => $host, 's' => $service, 'Debug' => $debug]);

if (empty($users)) {
    $cv = new CheckValue(array(
        'k'     => 'binary',
        'h'     => $host,
        's'     => $service,
        'Value'   => false,
        'Text'  => "No Users are locked",
        'State' => 0
    ));
    $cv->bye();
} else {
    foreach ($users as $user) {
        $cv = new CheckValue(array(
            'k'     => 'binary',
            'h'     => $host,
            's'     => "locked user " . $user['SamAccountName'],
            'Value'   => false,
            'Text'  => "User " . $user['SamAccountName'] . " is locked",
            'State' => 2
        ));
        $correlation->add($cv->getData());
    }
    $correlation->bye();
}

#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");
 
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\StateCorrelation;
 
if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}
 
$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;
 
CommandLine::terminateOnEmpty($host);

# optinales uebersteuern mittels FQDN, moeglicherweise wird dies in der Zukunft benoetigt
try {
   // [* CodeParam FQDN ; Value=develop.itdesign.at ; Desc=FQDN of the server *]
   $host = Common::getMonitoringPackParameter($OPT,'FQDN');
} catch (Exception $e) {
   fwrite(STDERR, print_r("FQDN Package Parameter not found, using $host\n",1));  
}

try {
   // [* CodeParam excludedServerComponentState ; Value=ForwardSyncDaemon,ProvisioningRps ; Desc=comma seperated list of components to exclude from the check *]
   $excludeList = Common::getMonitoringPackParameter($OPT,'excludedServerComponentState');
} catch (Exception $e) {
   $excludeList = "";
   fwrite(STDERR, print_r("excludedServerComponentState Package Parameter not found\n",1));  
}

try {
    // [* CodeParam PowershellScriptLocation ; Value=C:\ITdesign\Check-ExchangeComponentState.ps1 ; Desc=Location of the powershell script to execute *]
   $powershellScriptLocation = Common::getMonitoringPackParameter($OPT,'PowershellScriptLocation');
} catch (Exception $e) {
   print("PowershellScriptLocation Parameter not found");
   exit(3);
}

try {
  $remoteConnection = Common::getGlobalConfigParameter('WinProxyConnection');
} catch (Exception $e) {
  print "WinProxyConnection not set as parameter\n";
  exit (Constants::NUMERIC_CRITICAL);
}

$command = sprintf ('%s "powershell %s -Computername %s"',
                $remoteConnection, $powershellScriptLocation, $host);

exec ($command,$out,$exit);

if (empty($out)) {
  print "powershell command failed: $command";
  exit(Constants::NUMERIC_UNKNOWN);
}

if ($debug) {
    print ("command: $command\n");
    print ("exit:    $exit\n");
    print_r ($out);
    print ("\n");
}

$implodedOut = implode("", $out);
$jsonOut = json_decode($implodedOut,true);

$correlation = new StateCorrelation(['h' => $host, 's' => $service, 'Debug' => $debug]);

$excludeList = explode(",", $excludeList);

foreach ($jsonOut as $out) {
  $value = false;
  $exit = 2;
  if ($out["State"] === "Active" || in_array($out["Component"], $excludeList)) {
    $value = true;
    $exit = 0;
  }
  
  $checkValue = new CheckValue(array(
    'k'   => 'binary',
    'h'   => $host,
    's'   => $out["Component"],
    'Exit' => $exit,
    'Text' => $out["State"],
    'CriticalText' => $out["Component"] . " is " . $out["State"],
    'Value' => $value
  ));

  $correlation->add($checkValue);
}

$correlation->bye();

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
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;
  
CommandLine::terminateOnEmpty($host);

$printStderr = function (string $msg) {
  if (false) {
    fwrite(STDERR,"$msg\n");
  }
};
$cmd = "ssh $host who";
exec($cmd, $out, $exit);

$cv = new CheckValue([
            'k'    => 'gauge',
            'h'    => "$host",
            's'    => "$service",
            'Debug' => $debug,
]);

if (!is_array($out) || $exit > 0) {
    if ($convertUnknown) {
        $cv->add([
          'State' => Constants::OK,
          'Text'  => "Command failed: no valid output"
        ]);
    } else {
        $cv->add([
          'State' => Constants::UNKNOWN,
          'Text'  => "Command failed: no valid output"
        ]);
    }
    $cv->bye();
}
$users = count($out);

$cv->add([    
      'Text' =>  $users === 0 ? "No users logged in" : implode("<br>", $out),
      'Value' => $users,
]);
$cv->bye();

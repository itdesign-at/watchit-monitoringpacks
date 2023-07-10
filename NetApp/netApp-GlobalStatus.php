#!/usr/bin/env php
<?php
#
# Monitor NetApp global (health) state with SNMP
#
# http://www.circitor.fr/Mibs/Html/NETWORK-APPLIANCE-MIB.php
#
# snmpwalk -v2c -c public nas-itd-22 1.3.6.1.4.1.789.1.2.2.25.0
#   SNMPv2-SMI::enterprises.789.1.2.2.25.0 = STRING: "The system's global status is normal. "
#
#
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$keyword = $OPT['k'] ?? 'binary';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

$cv = new CheckValue([
    'k'      => 'binary',
    'h'      => "$host",
    's'      => "$service",
    'Debug'  => $debug,
]);

$snmp = new Snmp($host);
$reponse = $snmp->get(".1.3.6.1.4.1.789.1.2.2.25.0");

if ($reponse == Snmp::$ERROR) {
    if ($convertUnknown) {
        $cv->add([
            'State' => Constants::CRITICAL,
            'Text'  => Constants::NoDataViaSNMP,
        ]);
    } else {
        $cv->add([
            'State' => Constants::UNKNOWN,
            'Text' => Constants::NoDataViaSNMP,
        ]);
    }
    $cv->bye();
}

$found = false;
$state = Constants::CRITICAL;
if (trim($reponse) === "The system's global status is normal.") {
    $found = true;
    $state = Constants::OK;
}

$cv->add([
    'Value'  => $found,
    'Text'   => "$reponse",
    'State'  => $state,
]);

$cv->bye();

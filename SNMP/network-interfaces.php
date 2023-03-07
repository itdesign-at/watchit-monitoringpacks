#!/usr/bin/env php
<?php
/**
 * Read all interfaces with SNMP.
 * Added 2022-10-06, WN
 */
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\InterfaceTable;
use ITdesign\Utils\CommandLine;

const SnmpReader = "/opt/watchit/bin/pSnmp";

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

$interfaceTable = new InterfaceTable($OPT);

$allInterfaces = pSnmp($OPT);

if (empty($allInterfaces)) {
    $interfaceTable->commit();
    $output = json_encode([
        Constants::DSN => $interfaceTable->args['DSN'],
        Constants::Text => Constants::NoDataViaSNMP
    ]);
    print "$output\n";
    if ($convertUnknown) {
        exit (Constants::NUMERIC_CRITICAL);
    }
    exit (Constants::NUMERIC_UNKNOWN);
}

foreach ($allInterfaces as $oneInterface) {
    $interfaceTable->add($oneInterface);
}

$interfaceTable->bye();

/**
 * Return interface table data from the external "pSnmp" program.
 *
 * @param array $conf
 * @return array
 */
function pSnmp(array $conf): array
{

    global $host;
    global $debug;

    $allReadConfigFiles = [$conf['rc'] ?? '', "/opt/watchit/var/etc/ifTable-read-V1-config.yaml"];

    $found = false;
    foreach ($allReadConfigFiles as $readConfigFile) {
        if (is_readable($readConfigFile)) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        printf("$readConfigFile not found or not readable\n");
        exit(1);
    }

    $execHost = $conf['ExecHost'] ?? '';
    if ($execHost == '') {
        $execHost = $host;
    }

    // e.g. /opt/watchit/bin/pSnmp -out json -h localhost
    $cmd = sprintf("%s -out json -h '%s' -op entries -rc %s", SnmpReader, $execHost, $readConfigFile);

    if ($debug) {
        CheckValue::dbg(__FUNCTION__, "line: " . __LINE__, "\$cmd:" . $cmd);
    }

    /* read the interface table via external go program */
    exec($cmd, $out, $exit);

    $data = null;
    if (!empty($out)) {
        // $out[0] = one line of json output from pSnmp
        $data = json_decode($out[0], true);
    }

    if ($data === null) {
        return [];
    }

    /**
     * e.g. $data = Array
     * (
     * [0] => Array
     * (
     * [AdminStatus] => up
     * [Description] => lo
     * [Hash] => 8051b7a4
     * [IP] => 127.0.0.1
     * [In] => 524431484800
     * [Index] => 1
     * [OperStatus] => up
     * [Out] => 524431484800
     * [Speed] => 10000000
     * [SpeedReadable] => 10.00 Mbit
     * )
     */

    if (!array_key_exists("0", $data)) {
        return [];
    }

    return $data;
}

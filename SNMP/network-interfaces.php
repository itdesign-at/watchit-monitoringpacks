#!/usr/bin/env php
<?php
/**
 * Read all interfaces with SNMP.
 * Added 2022-10-06, WN
 */
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\InterfaceTable;
use ITdesign\Utils\CommandLine;
const SnmpReader = "/opt/watchit/bin/pSnmp";

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
if ($host == '') {
    print("option -h <host> is empty or missing\n");
    exit(1);
}

$interfaceTable = new InterfaceTable($OPT);

$allInterfaces = pSnmp($OPT);
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
    $cmd = sprintf("%s -out json -h %s -op entries -rc %s", SnmpReader, $execHost, $readConfigFile);

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

    return $data;
}

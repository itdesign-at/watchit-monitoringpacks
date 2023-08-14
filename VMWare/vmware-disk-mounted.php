<?php
#
# powershell script needed on bitvise server
#   C:/ITDESIGN/powershell-scripts/get-VMDisk-Mounted.ps1
#
#
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Net\Snmp;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

// for debugging
$printStderr = function (string $msg) {
    if (false) {
        fwrite(STDERR, "$msg\n");
    }
};

$correlation = new StateCorrelation(['h' => $host, 's' => $service, 'Debug' => $debug]);

$cmd = "ssh bitvise-server powershell C:/ITDESIGN/powershell-scripts/get-VMDisk-Mounted.ps1 -VMHost $host";

exec($cmd, $out, $exit);

if (empty($out)) {
    print('Command failed');
    exit(Constants::NUMERIC_UNKNOWN);
}

$output = implode('', $out);
$position = strpos($output, '[');
$fileOutput = substr($output, $position);
$disks = json_decode($fileOutput, 1);

foreach ($disks as $disk) {
    $value = true;
    $state = Constants::OK;
    if ($disk['Accessible'] !== 'True' || $disk['State'] !== 'Available') {
        $value = false;
        $state = Constants::CRITICAL;
    }
    $cv = new CheckValue([
        'k' => 'binary',
        'h' => $host,
        's' => $disk['Name'],
        'Text' => "Datacenter: $disk[Datacenter] <br/> Name: $disk[Name] <br/> Accessible: $disk[Accessible] <br/> State: $disk[State]",
        'State' => $state,
        'Value' => $value,
        'Debug' => $debug
    ]);
    $correlation->add($cv);
}
$correlation->bye();
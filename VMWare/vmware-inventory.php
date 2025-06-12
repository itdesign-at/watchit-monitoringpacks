#!/usr/bin/env php
<?php
#
# powershell script needed on bitvise server
#   C:/ITDESIGN/powershell-scripts/get-VMInventory.ps1
#
# Datacenter is needed and given via Code Parameter
#
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\StateCorrelation;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;
use ITdesign\Plugins\Constants;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

$printStderr = function (string $msg) {
    if (false) {
        fwrite(STDERR,"$msg\n");
    }
};


$datacenter ='';
try {
    // [* CodeParam datacenter ; Value=example-datacenter1 *]
    $datacenter = Common::getMonitoringPackParameter($OPT, 'datacenter');
} catch (Exception $e) {
    print('Code Parameter missing!');
    exit(3);
}

$cmd = "ssh bitvise-server powershell C:/ITDESIGN/powershell-scripts/get-VMInventory.ps1 -Datacenter $datacenter";
exec($cmd, $out, $exit);

$output = implode('', $out);
$position = strpos($output, '[');
$fileOutput = substr($output, $position);
$inventoryData = json_decode($fileOutput, 1);

$customTable = new StateCorrelation(['k' => 'vmwareInventoryTable', 'h' => "$host", 's' => "$service", 'Debug' => $debug]);
$customTable->setHeader(["Host","Guest OS","ESX Host","State","Ram","Cpus","Tools status"]);

foreach($inventoryData as $data) {
    $tableEntry = [];
    $tableEntry['Host'] = $data["GuestName"];
    $tableEntry['Guest OS'] = $data["GuestOS"];
    $tableEntry['ESX Host'] = $data["ESXHost"];
    $tableEntry['State'] = $data["VMStatus"];
    $tableEntry['Ram'] = $data["Ram"];
    $tableEntry['Cpus'] = $data["Cpus"];
    $tableEntry['Tools status'] = $data["ToolsStatus"];
    $customTable->add($tableEntry);
}

$customTable->init();
$customTable->set('Text', 'Click here to view inventory');
$customTable->set('Exit', 0);
$customTable->commit();
print $customTable->getOutput();
$exitCode = $customTable->getExit();
exit($exitCode);


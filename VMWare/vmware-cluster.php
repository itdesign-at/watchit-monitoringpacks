#!/usr/bin/env php
<?php
#
# powershell script needed in a Job
# Example cron job:
#
#   cat /etc/cron.d/vmware-checks | grep VMCluster
#   */5 * * * * nagios ssh bitvise-server 'powershell C:/ITDESIGN/powershell-scripts/get-VMCluster.ps1' > /opt/watchit/var/data/customer/vmware/VMClusters.json
#
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\StateCorrelation;
use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\Plugin;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\Common;
use ITdesign\Utils\FilterThreshold;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$host = $OPT['h'] ?? '';
$address = $OPT['a'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

$file = '/opt/watchit/var/data/customer/vmware/VMClusters.json';
$output = file_get_contents($file);
$position = strpos($output, '[');
$fileOutput = substr($output, $position);
$allClusters = json_decode($fileOutput, 1);

if ($allClusters === NULL) {
    print "json_decode went wrong\n";
    exit(Constants::NUMERIC_UNKNOWN);
}

$s = substr($service, 15);
$clusters = array_column($allClusters, 'Cluster');
$key = array_search($s, $clusters);

if (!$key) {
    print "Cluster not found\n";
    exit(Constants::NUMERIC_UNKNOWN);
}

$cluster = $allClusters[$key];

$clusterString = '';
foreach ($cluster as $key => $value) {
    if (is_array($value)) {
        $value = implode('<br>', $value);
    }
    $clusterString .= "$key: $value<br>";
}
$clusterString = rtrim($clusterString, ", ");

$cv = new CheckValue([
    'k' => 'gauge',
    's' => $cluster['Cluster'],
    'State' => Constants::OK,
    'Value' => $cluster['TolerateRemaining'],
    'Text' => $clusterString,
    'Debug' => $debug
]);

$cv->bye();

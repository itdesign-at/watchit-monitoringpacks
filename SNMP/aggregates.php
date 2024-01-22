#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Plugins\Plugin;
use ITdesign\Plugins\StorageTable;
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
$convertUnknown = $OPT['convertUnknown'] ?? false;

CommandLine::terminateOnEmpty($host);

$storageTable = new StorageTable(['h' => $host, 's' => $service, 'Debug' => $debug]);

$diskFilter = FilterThreshold::getIncludeFilter(array("h" => $OPT["h"], "section" => "disk", "Debug" => $debug));
$diskData = getStorageTable($host);

/* store number of disks datasets we got from the net */
$n = count($diskData);

if ($n < 1) {
    $cvNoData = new CheckValue(['h' => $OPT['h'], 'k' => "storageEntry", 'Text' => $OPT['UnknownText']]);
    foreach ($cvNoData as $data) {
        $storageTable->add($data);
    }
    return;
}

foreach ($diskData as $struct) {
    
    /* convert snmp structure to a full mbc dataset */
    $data = Common::mbc($struct);

    /* if filter present and partition filtered by Plugin::compare -> skip */

    if (!empty($diskFilter) && !Plugin::compare($diskFilter, $data)) {
        continue;
    }
    
    /* check if current volume name has .snapshot at the end -> skip */
    if (preg_match("/\.snapshot$/",$data['Description'])) {
        continue; 
    }
    
    /* check if current volume name has .. at the end -> skip */
    if (preg_match("/\.\.$/",$data['Description'])) {
        continue; 
    }
    
    /* check if current volume has a slash in front (e.g. /vol) -> skip */
    if (preg_match("/^\/.*/",$data['Description'])) {
        continue; 
    }
   
    $th = FilterThreshold::getThreshold(array(
        'h'       => $OPT['h'],
        's'       => $data['Description'],
        'section' => "disk",
        'Debug'   => "$debug"
    ));

    $checkValue = new CheckValue([
        'k'            => "storgeEntry",
        'h'            => $OPT['h'],
        's'            => $data['Description'],
        'w'            => $th['w'],
        'c'            => $th['c'],
        Constants::Text => $data['Summary'] ?? 'Summary not set',
        'Value'          => sprintf('%s;%s', $data['Size'], $data['Used']),
        'Debug'        => $debug,
        'Description' =>  $data['Description'],
    ]);
    
    $checkValue->add($data);
    $storageTable->add($checkValue->getData());
}

$storageTable->bye();

function correctBufferOverflow($what)
{
  if ($what < 0) {
      $what = $what + pow(2, 32);
  }
  return $what;
}

function get($oid,$host)
{
  $snmp = new Snmp($host);
  $snmpValue = $snmp->get($oid);
  
  if($snmpValue == Snmp::$ERROR){
        $checkValue = new CheckValue([
          'Text'   => Constants::NoDataViaSNMP,
          'State'  => Constants::UNKNOWN,
        ]);
        $checkValue->bye();
    }
  
  return $snmpValue;
}

function walkOID($oid,$host)
{
  $snmp = new Snmp($host);
  $snmpValues = $snmp->walkOID($oid);
  return $snmpValues;
}

function getStorageTable($host)
{
    $result = array();
    $storageDescriptionOID = '.1.3.6.1.4.1.789.1.5.4.1.2';
    $dfHighTotalKBytesOID = '.1.3.6.1.4.1.789.1.5.4.1.14';
    $dfLowTotalKBytesOID = '.1.3.6.1.4.1.789.1.5.4.1.15';
    $dfHighUsedKBytesOID = '.1.3.6.1.4.1.789.1.5.4.1.16';
    $dfLowUsedKBytesOID = '.1.3.6.1.4.1.789.1.5.4.1.17';

    $snmpData = walkOID($storageDescriptionOID, $host);    
    
    if(empty($snmpData)){
        $checkValue = new CheckValue([
          'Text'   => Constants::NoDataViaSNMP,
          'State'  => Constants::UNKNOWN,
        ]);
        $checkValue->bye();
    }
    
    foreach ($snmpData as $oid => $description) {
        $aTmp = explode('.', $oid);
        $index = end($aTmp);

        $totalHigh = correctBufferOverflow(get($dfHighTotalKBytesOID . ".$index", $host));
        $totalLow = correctBufferOverflow(get($dfLowTotalKBytesOID . ".$index", $host));
        $usedHigh = correctBufferOverflow(get($dfHighUsedKBytesOID . ".$index", $host));
        $usedLow = correctBufferOverflow(get($dfLowUsedKBytesOID . ".$index", $host));

        $struct['Description'] = $description;
        $struct['Max'] = intVal($totalHigh) * pow(2, 32) + intVal($totalLow);
        $struct['Used'] = intVal($usedHigh) * pow(2, 32) + intVal($usedLow);
        $struct['Factor'] = 1024;
        $result[] = $struct;
    }
    return $result;
}

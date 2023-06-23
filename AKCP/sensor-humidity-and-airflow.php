#!/usr/bin/env php
<?php

/**
 * Query AKCP Humidity and Airflow Sensor
 *
 * http://www.akcp.com/industry-news/nagios-xi-5-snmp-temperature-sensor-integration-guide/
 * http://www.mibdepot.com/cgi-bin/getmib3.cgi?win=mib_a&i=1&n=HHMSSMALL-MIB&r=akcp&f=smallsa.mib&v=v1&t=sca&o=hhmsSensorArrayTempStatus
 * https://www.didactum-security.com/files/manuals/AKCP_OID_SNMP%20manual.pdf
 *
 * Test sensor response with snmpwalk:
 *
 * # snmpwalk -On -v 1 -c <read-community> sensor-itd-01 .1.3.6.1.4.1.3854.1.2.2.1.17.1.4
 * .1.3.6.1.4.1.3854.1.2.2.1.17.1.4.0 = INTEGER: 2
 * .1.3.6.1.4.1.3854.1.2.2.1.17.1.4.1 = INTEGER: 0
 * .1.3.6.1.4.1.3854.1.2.2.1.17.1.4.2 = INTEGER: 0
 * .1.3.6.1.4.1.3854.1.2.2.1.17.1.4.3 = INTEGER: 0
 * .1.3.6.1.4.1.3854.1.2.2.1.17.1.4.4 = INTEGER: 2
 * .1.3.6.1.4.1.3854.1.2.2.1.17.1.4.5 = INTEGER: 2
 * .1.3.6.1.4.1.3854.1.2.2.1.17.1.4.6 = INTEGER: 2
 * .1.3.6.1.4.1.3854.1.2.2.1.17.1.4.7 = INTEGER: 0
 */

require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\StateCorrelation;
use ITdesign\Net\Snmp;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;
use ITdesign\Utils\FilterThreshold;
use ITdesign\Utils\Common;

define ('SENSOR_STATUS_NORMAL','2');

# Sensor types from OID .1.3.6.1.4.1.3854.1.2.2.1.18.1.9.<index>
# --------------------------------------------------------------
# temperature(1),
# 4-20mA(2),
# humidity(3),
# water(4),
# atod(5),
# security(6),
# airflow(8),
# siren(9),
# dryContact(10),
# voltage(12),
# relay(13),
# motion(14)
define ('SENSOR_TYPE_HUMIDITY','3');
define ('SENSOR_TYPE_AIRFLOW','8');

$BASEOID = ".1.3.6.1.4.1.3854.1.2.2.1.17.1";

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}

$keyword = $OPT['k'] ?? '';
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

$correlation = new StateCorrelation(['h' => "$host", 's' => "$service", 'Debug' => $debug]);

$cv = new CheckValue([
    'k'    => 'gauge',
    'h'    => "$host",
    's'    => "$service",
    'Debug' => $debug,
]);

$snmp = new Snmp($host);
$statusOids = $snmp->walkOID($BASEOID . '.4');

if ($statusOids == Snmp::$ERROR || empty($statusOids)) {
    $cv->add([
        'Text'   => Constants::NoDataViaSNMP,
        'State'  => Constants::UNKNOWN,
    ]);
    $cv->bye();
}

$sensorCount = count($statusOids);
$counterSensorsNotNormal = 0;

foreach ($statusOids as $oid => $status) {
    if ($status != SENSOR_STATUS_NORMAL) {
        if ($debug) {
            print "Skip sensor '$oid' with status '$status'\n";
        }
        $counterSensorsNotNormal++;
        continue;
    }

    $index = explode('.',$oid);
    $index = end($index);

    /* Description configured in the sensor e.g. 'Humidity Server Room' */
    $description = $snmp->get(array ('oid' => $BASEOID . ".1.$index"));

    /* Humidity or Airflow in percent */
    $value = $snmp->get(array ('oid' => $BASEOID . ".3.$index"));

    /* Type of sensor */
    $sensorType = $snmp->get(array ('oid' => '.1.3.6.1.4.1.3854.1.2.2.1.18.1.9' . ".$index"));

    if ($sensorType == SENSOR_TYPE_HUMIDITY) {
        $section = 'humidity';
    } else if ($sensorType == SENSOR_TYPE_AIRFLOW) {
        $section = 'airflow';
    } else {
        /* just those two sensors are currently implemented */
        continue;
    }

    if ($debug) {
        print "Oid = $oid, Description = $description, Value = $value%, section = $section\n";
    }

    $th = FilterThreshold::getThreshold(array(
        'h'       => $host,
        's'       => $description,
        'section' => "humidity",
        'Debug'   => "$debug"
    ));

    $cv->add([
        'Counter' => $value,
        'c'       => $th['c'],
        'w'       => $th['w'],
        's'       => "$s - $description",
        'Value'   => $value,
        'Text'    => "$description: $value%"
    ]);
    $correlation->add($cv);
}

if ($counterSensorsNotNormal === $sensorCount) {
    $cv->add([
        'Text'   => Constants::NoDataViaSNMP,
        'State'  => Constants::UNKNOWN,
    ]);
    $cv->bye();
}

$correlation->bye();
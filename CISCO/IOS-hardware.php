<?php
#!/usr/bin/env php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

/**
 * Query Cisco ENVMON MIB for cisco switches and get
 * HW sensor status with SNMP.
 *
 * http://www.oidview.com/mibs/9/CISCO-ENVMON-MIB.html
 *
 */

use ITdesign\Net\Snmp;
use ITdesign\Plugins\StateCorrelation;
use ITdesign\Plugins\CheckValue;
use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}
$host = $OPT['h'] ?? '';
$service = $OPT['s'] ?? '';
$debug = $OPT['Debug'] ?? false;

CommandLine::terminateOnEmpty($host);

# define all sensor types
$sensors = array (
    # ciscoEnvMonTemperature
    array (
        "Name"   => "1.3.6.1.4.1.9.9.13.1.3.1.2",
        "Status" => "1.3.6.1.4.1.9.9.13.1.3.1.6"
    ),
    # ciscoEnvMonSupply
    array (
        "Name"   => "1.3.6.1.4.1.9.9.13.1.5.1.2",
        "Status" => "1.3.6.1.4.1.9.9.13.1.5.1.3"
    ),
    # ciscoEnvMonVoltage
    array (
        "Name"   => "1.3.6.1.4.1.9.9.13.1.2.1.2",
        "Status" => "1.3.6.1.4.1.9.9.13.1.2.1.3"
    ),
    # ciscoEnvMonFan
    array (
        "Name"   => "1.3.6.1.4.1.9.9.13.1.4.1.2",
        "Status" => "1.3.6.1.4.1.9.9.13.1.4.1.3"
    )
);

$count = 0;
$sensorNames = array();

$snmp = new Snmp($host);
$correlation = new StateCorrelation(['h' => "$h", 's' => "$s"]);
$counterEmptySensors = 0;

# foreach sensor type
foreach ($sensors as $sensor) {
    # read each of the sensor names
    $sensorNamesSnmp = $snmp->walk(array("oid" =>$sensor["Name"]));

    # read each of the sensor status
    $sensorStatusSnmp = $snmp->walk(array("oid" =>$sensor["Status"]));


    if (empty($sensorNamesSnmp)) {
        $counterEmptySensors++;
    }

    # get a whole sum of sensors
    $count++;

    # foreach sensor
    foreach ($sensorNamesSnmp as $key => $sensorName) {
        # get status from status array
        $sensorStatus = $sensorStatusSnmp[$key];
        /*
           Hardware sensor status from the SNMP MIB:
           normal           (1)
           warning          (2)
           critical         (3)
           shutdown         (4)
           notPresent       (5)
           notFunctioning   (6)
        */

        if ($sensor["Name"] == '1.3.6.1.4.1.9.9.13.1.2.1.2') {
            $k = 'gauge'; /* ciscoEnvMonVoltage: voltage output in mV */
            $value = intval($sensorStatus);
        } else {
            $k = 'binary';
            if ("$sensorStatus" == "5") { /* = notPresent */
                continue;
            }
            if ("$sensorStatus" == "1") {
                $state = "OK";
                $text = 'normal';
                $value = true;
            } else {
                $state = "CRITICAL";
                $value = false;
                if ("$sensorStatus" == "2") {
                    $text = "warning";
                } else if ("$sensorStatus" == "3") {
                    $text = "critical";
                } else if ("$sensorStatus" == "4") {
                    $text = "shutdown";
                } else if ("$sensorStatus" == "6") {
                    $text = "notFunctioning";
                } else {
                    $text = "unknown";
                }
            }
        }

        $checkValue = new CheckValue(array(
            'k'     => $k,
            'h'     => $h,
            's'     => "$s - $sensorName",
            'Value' => $value,
            'Text'  => "$sensorName is $text",
            'State' => $state,
            'Debug' => $debug
        ));
        $correlation->add($checkValue);
    }
}

if ($counterEmptySensors === $count) {
    print Constants::NoDataViaSNMP;
    exit(3);
}

$correlation->bye();

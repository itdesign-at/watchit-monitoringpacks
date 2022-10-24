#!/usr/bin/env php
<?php
require_once("/opt/watchit/sources/php/vendor/autoload.php");

use ITdesign\Plugins\Constants;
use ITdesign\Utils\CommandLine;

const f = "/opt/watchit/var/etc/hosts-exported.json";

if (!isset($OPT)) {
    $OPT = CommandLine::getCommandLineOptions($argv);
}
$host = $OPT['h'] ?? '';
if ($host === '') {
    print "host is empty or missing\n";
    exit(Constants::NUMERIC_UNKNOWN);
}
$content = file_get_contents(f);
if ($content === false) {
    printf("unable to load %s\n", f);
    exit(3);
}
$data = json_decode($content, true);
if ($data === null) {
    printf("unable to json_decode %s\n", f);
    exit(3);
}
if (!array_key_exists($host, $data)) {
    printf("host '%s' not found in file '%s'\n", $host, f);
    exit(Constants::NUMERIC_OK);
}
$hostData = $data[$host];
$description = $hostData['D'] ?? '';
if ($description === '') {
    printf("host '%s' has no description\n", $host);
} else {
    print "$description\n";
}

exit(Constants::NUMERIC_OK);


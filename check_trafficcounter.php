#!/usr/bin/env php
<?php

$filename = basename(__FILE__);
if (substr($argv[1] ?? "", -strlen($filename)) == $filename) array_shift($argv);
if (substr($argv[0] ?? "", -strlen($filename)) == $filename) array_shift($argv);
if (count($argv) < 4) {
	echo "USAGE: " . $filename . " Host SNMP InOID OutOID\n";
	exit(3);
}

$time = time();
$snmp = new SNMP(SNMP::VERSION_2C, $argv[0], $argv[1]);
$snmp->valueretrieval = SNMP_VALUE_PLAIN;
$snmp = @$snmp->get(array($argv[2], $argv[3]), TRUE);
if (!$snmp || count($snmp) != 2) {
	echo "UNKNOWN: No data\n";
	exit(3);
}

$cache = __DIR__ . "/cache/" . $filename . "-" . $argv[0] . "-" . $argv[2] . "-" . $argv[3];
if (file_exists($cache)) {
	$data = json_decode(file_get_contents($cache), TRUE);
} else {
	$data = array("time" => 0);
}

if (date("Ym", $data['time']) != date("Ym", $time)) {
	$stats = "Last values before reset: " . date("Y-m-d H:i:s", $data['time']) . "\t"
		. "IN: " . number_format($data['count_in'], 0) . " Byte\t"
		. "OUT: " . number_format($data['count_out'], 0) . " Byte\t"
		. "TOTAL: " . number_format($data['count_in'] + $data['count_out'], 0) . " Byte\n";
	file_put_contents($cache . ".log", $stats, FILE_APPEND);
	$data['count_in'] = 0;
	$data['count_out'] = 0;
} else {
	if ($data['last_in'] > $snmp[$argv[2]]) $data['last_in'] = 0;
	if ($data['last_out'] > $snmp[$argv[3]]) $data['last_out'] = 0;

	$data['count_in'] += $snmp[$argv[2]] - $data['last_in'];
	$data['count_out'] += $snmp[$argv[3]] - $data['last_out'];
}

$data['time'] = $time;
$data['last_in'] = $snmp[$argv[2]];
$data['last_out'] = $snmp[$argv[3]];
file_put_contents($cache, json_encode($data));

echo "IN: " . number_format($data['count_in'] / 1000 / 1000 / 1000, 2) . "GB ";
echo "OUT: " . number_format($data['count_out'] / 1000 / 1000 / 1000, 2) . "GB ";
echo "TOTAL: " . number_format(($data['count_in'] + $data['count_out']) / 1000 / 1000 / 1000, 2) . "GB|";
echo "in=" . $data['count_in'] . "B out=" . $data['count_out'] . "B total=" . ($data['count_in'] + $data['count_out']) . "B\n";
exit(0);

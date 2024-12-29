#!/usr/bin/env php
<?php

$filename = basename(__FILE__);
if (substr(@$argv[1], -strlen($filename)) == $filename) array_shift($argv);
if (substr(@$argv[0], -strlen($filename)) == $filename) array_shift($argv);
if (count($argv) < 2) {
	echo "USAGE: " . $filename . " Host SNMP [InterfaceFilter] [OverrideSpeed >= 1000] [Warning] [Critical]\n";
	exit(3);
}

$time = time();
$warn = $argv[4] ?? 80;
$crit = $argv[5] ?? 90;
$snmp = new SNMP(SNMP::VERSION_2C, $argv[0], $argv[1]);
$snmp->valueretrieval = SNMP_VALUE_PLAIN;

$oids = array(
	"name" => "1.3.6.1.2.1.31.1.1.1.1",
	"alias" => "1.3.6.1.2.1.31.1.1.1.18",
	"speed" => "1.3.6.1.2.1.31.1.1.1.15",
	"in" => "1.3.6.1.2.1.31.1.1.1.6",
	"out" => "1.3.6.1.2.1.31.1.1.1.10",
);
foreach ($oids as $type => $oid) {
	$data[$type] = @$snmp->walk($oid, TRUE);
	if ($type == "in" && !$data[$type]) $data[$type] = @$snmp->walk("1.3.6.1.2.1.2.2.1.10", TRUE);
	if ($type == "out" && !$data[$type]) $data[$type] = @$snmp->walk("1.3.6.1.2.1.2.2.1.16", TRUE);
	if (!$data[$type] || !count($data[$type])) {
		echo "UNKNOWN: No data\n";
		exit(3);
	}
}
$snmp = $data;

$interfaces = $snmp['name'];
foreach ($snmp['alias'] as $key => $alias) {
	if ($alias && $alias != "defconf") $interfaces[$key] = $alias;
}
if (isset($argv[2])) $interfaces = preg_grep("/^" . trim($argv[2], "^$") . "$/i", $interfaces);
if (!count($interfaces)) {
	echo "UNKNOWN: No interfaces found\n";
	exit(3);
}

$exit = 0;
$echo = "";
$perf = "|";
$single = count($interfaces) == 1;

$cache = __DIR__ . "/cache/" . $filename . "-" . $argv[0] . (isset($argv[2]) ? "-" . preg_replace("/[^a-zA-Z0-9]/", "", $argv[2]) : "");
if (file_exists($cache) && $time - filemtime($cache) <= 86400) {
	$data = json_decode(file_get_contents($cache), TRUE);
	if (isset($data['time']) && $data['time'] > 0 && $data['time'] < $time) {
		$diff = $time - $data['time'];

		function bits2human($bits) {
			$exponent = intval(log($bits) / log(1000));
			$symbols = array("bps", "kbps", "Mbps", "Gbps", "Tbps");
			return number_format($bits / 1000 ** $exponent, $exponent ? 1 : 0, ".", "") . $symbols[$exponent];
		}

		$interfaces = array_flip($interfaces);
		ksort($interfaces, SORT_NATURAL);
		foreach ($interfaces as $interface => $id) {
			if (!isset($data['in'][$id]) || !isset($data['out'][$id]) || $snmp['in'][$id] < $data['in'][$id] || $snmp['out'][$id] < $data['out'][$id]) continue;

			$in = round(($snmp['in'][$id] - $data['in'][$id]) * 8 / $diff);
			$out = round(($snmp['out'][$id] - $data['out'][$id]) * 8 / $diff);
			$speed = (isset($argv[3]) && $argv[3] >= 1000) ? $argv[3] : ($snmp['speed'][$id] ? $snmp['speed'][$id] * 1000000 : "");
			$echo .= ($single ? " " : "\n") . $interface . " IN=" . bits2human($in) . " OUT=" . bits2human($out) . ($speed ? " SPEED=" . bits2human($speed) : "");

			if ($speed) {
				if ($single) {
					$interface = "";
					$perf .= " 'usage_in'=" . round($in / $speed * 100) . "%;" . $warn . ";" . $crit . " 'usage_out'=" . round($out / $speed * 100) . "%;" . $warn . ";" . $crit;
					if ($exit != 2 && ($in >= $speed * $crit / 100 || $out >= $speed * $crit / 100)) $exit = 2;
					if ($exit == 0 && ($in >= $speed * $warn / 100 || $out >= $speed * $warn / 100)) $exit = 1;
					$speed = ";" . $speed * $warn /100 . ";" . $speed * $crit /100 . ";0;" . $speed;
				} else $speed = ";;;0;" . $speed;
			}
			$perf .= " '" . ($interface ? $interface . "_" : "") . "traffic_in'=" . $in . $speed . " '" . ($interface ? $interface . "_" : "") . "traffic_out'=" . $out . $speed;
		}
		$echo .= str_replace("| ", "|", $perf);
	}
}

$snmp = array("time" => $time, "in" => $snmp['in'], "out" => $snmp['out']);
file_put_contents($cache, json_encode($snmp));
$echo .= "\n";

if ($exit == 2) {
	echo "CRITICAL:" . $echo;
} elseif ($exit == 1) {
	echo "WARNING:" . $echo;
} else {
	echo "OK:" . $echo;
}
exit($exit);

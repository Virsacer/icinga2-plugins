#!/usr/bin/env php
<?php

$filename = basename(__FILE__);
if (substr($argv[1] ?? "", -strlen($filename)) == $filename) array_shift($argv);
if (substr($argv[0] ?? "", -strlen($filename)) == $filename) array_shift($argv);
if (count($argv) < 4) {
	echo "USAGE: " . $filename . " RouterOS[:Port] Username Password (CPU|HDD|NTP|RAM|TEMP) [Warning Critical]\n";
	exit(3);
}

if (isset($argv[5])) {
	$warn = min(intval($argv[4]), intval($argv[5]));
	$crit = max(intval($argv[4]), intval($argv[5]));
} elseif ($argv[3] == "HDD") {
	$warn = 95;
	$crit = 98;
} elseif ($argv[3] == "NTP") {
	$warn = 0.5;
	$crit = 1;
} elseif ($argv[3] == "TEMP") {
	$warn = 70;
	$crit = 80;
} else {
	$warn = 80;
	$crit = 90;
}

preg_match("/^([^:]+)(:([0-9]+))?$/", $argv[0], $data);
if (!$data) preg_match("/^\[([^\]]+)\](:([0-9]+))$/", $argv[0], $data);
$ssh = ssh2_connect($data[1] ?? $argv[0], $data[3] ?? 22);
if (!$ssh) {
	echo "UNKNOWN: Connection failed\n";
	exit(3);
}
if (!ssh2_auth_password($ssh, $argv[1], $argv[2])) {
	echo "UNKNOWN: Login failed\n";
	exit(3);
}

switch ($argv[3]) {
	case "CPU":
		sleep(3);
		$stream = ssh2_exec($ssh, ":put [/system/resource/get cpu-load]");
		stream_set_blocking($stream, TRUE);
		$data = trim(stream_get_contents($stream));
		if (!is_numeric($data)) {
			echo "UNKNOWN: No data\n";
			exit(3);
		}
		$echo = $data . "%|load=" . $data . "%;" . $warn . ";" . $crit . "\n";
		break;
	case "HDD":
	case "RAM":
		if ($argv[3] == "HDD") $stream = ssh2_exec($ssh, ":put ([/system/resource/get free-hdd-space] . \"+\" . [/system/resource/get total-hdd-space])");
		if ($argv[3] == "RAM") $stream = ssh2_exec($ssh, ":put ([/system/resource/get free-memory] . \"+\" . [/system/resource/get total-memory])");
		stream_set_blocking($stream, TRUE);
		$data = trim(stream_get_contents($stream));
		$data = explode("+", $data);
		if (count($data) != 2) {
			echo "UNKNOWN: No data\n";
			exit(3);
		}
		$data = array_combine(array("free", "total"), $data);
		$data['used'] = $data['total'] - $data['free'];
		$data['warn'] = round($data['total'] * $warn / 100);
		$data['crit'] = round($data['total'] * $crit / 100);
		$data['percent'] = round($data['used'] / $data['total'] * 100);
		$symbols = array("B", "KiB", "MiB", "GiB");
		$exponent = intval(log($data['used']) / log(1024));
		$data['used2'] = number_format($data['used'] / 1024 ** $exponent, $exponent ? 1 : 0, ".", "") . " " . $symbols[$exponent];
		$exponent = intval(log($data['total']) / log(1024));
		$data['total2'] = number_format($data['total'] / 1024 ** $exponent, $exponent ? 1 : 0, ".", "") . " " . $symbols[$exponent];
		$echo = $data['percent'] . "% " . $data['used2'] . "/" . $data['total2'] . "|used=" . $data['used'] . "B;" . $data['warn'] . ";" . $data['crit'] . ";0;" . $data['total'] . "\n";
		$data = $data['percent'];
		break;
	case "NTP":
		$stream = ssh2_exec($ssh, ":put [/system/ntp/client/get system-offset]");
		stream_set_blocking($stream, TRUE);
		$data = trim(stream_get_contents($stream));
		if (!is_numeric($data)) {
			echo "UNKNOWN: No data\n";
			exit(3);
		}
		$data /= 1000000;
		$echo = "Offset " . $data . " secs|offset=" . $data . "s;" . -$warn . ":" . $warn . ";" . -$crit . ":" . $crit . "\n";
		$data = abs($data);
		break;
	case "TEMP":
		$stream = ssh2_exec($ssh, "/system/health/print");
		stream_set_blocking($stream, TRUE);
		$data = trim(stream_get_contents($stream));
		preg_match_all("/ ([a-z]+-temperature[0-9]*) *([0-9]+)/", $data, $data);
		if (!count($data[1]) || !count($data[2])) {
			echo "UNKNOWN: No data\n";
			exit(3);
		}
		$data = array_combine($data[1], $data[2]);
		$echo = $data['cpu-temperature'] . "°C|";
		ksort($data);
		foreach ($data as $key => $val) {
			$echo .= " " . $key . "=" . $val;
			if ($key == "cpu-temperature") $echo .= ";" . $warn . ";" . $crit;
		}
		$echo = str_replace("| ", "|", $echo . "\n");
		$data = $data['cpu-temperature'];
		break;
	default:
		echo "UKNOWN: Mode parameter needs to be 'CPU', 'HDD', 'NTP', 'RAM' or 'TEMP'\n";
		exit(3);
}

if ($data >= $crit) {
	echo "CRITICAL: " . $echo;
	exit(2);
} elseif ($data >= $warn) {
	echo "WARNING: " . $echo;
	exit(1);
} else {
	echo "OK: " . $echo;
	exit(0);
}

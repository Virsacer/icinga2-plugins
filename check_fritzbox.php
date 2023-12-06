#!/usr/bin/env php
<?php

$filename = basename(__FILE__);
if (substr(@$argv[1], -strlen($filename)) == $filename) array_shift($argv);
if (substr(@$argv[0], -strlen($filename)) == $filename) array_shift($argv);
if (count($argv) < 4) {
	echo "USAGE: " . $filename . " Fritzbox[:Port] Username Password (CPU|RAM|TEMP) [Warning Critical]\n";
	exit(3);
}

curl_setopt_array($curl = curl_init(), array(
	CURLOPT_URL => "http://" . $argv[0] . "/data.lua",
	CURLOPT_CONNECTTIMEOUT => 30,
	CURLOPT_FOLLOWLOCATION => TRUE,
	CURLOPT_RETURNTRANSFER => TRUE,
	CURLOPT_SSL_VERIFYHOST => FALSE,
));

$session = array();
$sessions = __DIR__ . "/cache/" . $filename . "-sessions";
if (file_exists($sessions)) {
	$session = json_decode(file_get_contents($sessions), TRUE);
	if (isset($session[$argv[0]])) {
		curl_setopt($curl, CURLOPT_POSTFIELDS, "page=ecoStat&sid=" . $session[$argv[0]]);
		$data = curl_exec($curl);
	}
}

if (!isset($data) || $data == "" || strpos($data, "\"sid\":\"0000000000000000\"") !== FALSE) {
	libxml_set_streams_context(stream_context_create(array("ssl" => array("verify_peer" => FALSE, "verify_peer_name" => FALSE))));
	$xml = simplexml_load_file("https://" . $argv[0] . "/login_sid.lua");
	$xml = simplexml_load_file("https://" . $argv[0] . "/login_sid.lua?sid=" . $xml->SID . "&username=" . $argv[1] . "&response=" . $xml->Challenge . "-" . md5(mb_convert_encoding($xml->Challenge . "-" . $argv[2], "UCS-2LE", "UTF-8")));
	if ($xml->SID == "0000000000000000") {
		echo "UNKNOWN - Login failed";
		exit(3);
	}
	$session[$argv[0]] = strval($xml->SID);
	file_put_contents($sessions, json_encode($session));
	curl_setopt($curl, CURLOPT_POSTFIELDS, "page=ecoStat&sid=" . $xml->SID);
	$data = curl_exec($curl);
}

curl_close($curl);
$data = json_decode($data, TRUE);

if (isset($argv[5])) {
	$warn = min(intval($argv[4]), intval($argv[5]));
	$crit = max(intval($argv[4]), intval($argv[5]));
} elseif ($argv[3] == "RAM") {
	$warn = 85;
	$crit = 90;
} elseif ($argv[3] == "TEMP") {
	$warn = 90;
	$crit = 100;
} else {
	$warn = 80;
	$crit = 90;
}

switch ($argv[3]) {
	case "CPU":
		if (!isset($data['data']['cpuutil']['series'][0])) {
			echo "UNKNOWN - No data";
			exit(3);
		}
		$data = end($data['data']['cpuutil']['series'][0]);
		$out = $data . "%|load=" . $data . "%;" . $warn . ";" . $crit;
		break;
	case "RAM":
		if (!isset($data['data']['ramusage']['series'][2])) {
			echo "UNKNOWN - No data";
			exit(3);
		}
		$data = 100 - end($data['data']['ramusage']['series'][2]);
		$out = $data . "%|used=" . $data . "%;" . $warn . ";" . $crit;
		break;
	case "TEMP":
		if (!isset($data['data']['cputemp']['series'][0])) {
			echo "UNKNOWN - No data";
			exit(3);
		}
		$data = end($data['data']['cputemp']['series'][0]);
		$out = $data . "°C|Temp=" . $data . ";" . $warn . ";" . $crit;
		break;
	default:
		echo "UKNOWN - Mode parameter needs to be 'CPU', 'RAM' or 'TEMP'";
		exit(3);
}

if ($data >= $crit) {
	echo "CRITICAL - " . $out;
	exit(2);
} elseif ($data >= $warn) {
	echo "WARNING - " . $out;
	exit(1);
} else {
	echo "OK - " . $out;
	exit(0);
}

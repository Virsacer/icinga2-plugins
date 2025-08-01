#!/usr/bin/env php
<?php

#Greenbone API -> see https://docs.greenbone.net/API/GMP/gmp-9.0.html

$filename = basename(__FILE__);
if (substr($argv[1] ?? "", -strlen($filename)) == $filename) array_shift($argv);
if (substr($argv[0] ?? "", -strlen($filename)) == $filename) array_shift($argv);
if (count($argv) < 2) {
	echo "USAGE: " . $filename . " Username Password [[Task:Age,]DefaultAge] [Task] [CriticalSeverity] [WarningSeverity]\n";
	exit(3);
}

$cli = "docker exec greenbone-gvm-1 sudo -u daemon /gvm/bin/gvm-cli --gmp-username " . $argv[0] . " --gmp-password " . $argv[1] . " tls --hostname 127.0.0.1 --port 9390";
if (isset($argv[3]) && preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/", $argv[3])) {
	$tasks = array($argv[3] => $argv[3]);
} else {
	$while = 0;
	do {
		if ($while++ == 3) {
			echo "UNKNOWN: Unable to read tasks\n";
			exit(3);
		}
		if ($while > 1) sleep(1);
		$data = shell_exec($cli . " --xml '<get_tasks/>'");
	} while (!$data);
	$data = preg_replace("/.*<get_tasks_response/s", "<get_tasks_response", $data);
	$xml = simplexml_load_string($data);
	foreach ($xml->task as $task) {
		$tasks[$task->name[0]->__tostring()] = $task->attributes()->id->__tostring();
	}
	if (isset($argv[3]) && !array_key_exists($argv[3], $tasks)) {
		echo "UNKNOWN: Unable to find task '" . $argv[3] . "'\n";
		exit(3);
	}
}

$age = array("!" => 0);
$argv[2] = explode(",", $argv[2] ?? "");
foreach ($argv[2] as $data) {
	$data = explode(":", $data);
	$age[isset($data[1]) ? $data[0] : "!"] = $data[1] ?? ($data[0] != "" ? $data[0] : 0);
}

$exit = 0;
$echo = "";
$result = array("high" => 0, "medium" => 0, "low" => 0, "log" => 0);

foreach ($tasks as $name => $id) {
	if (isset($argv[3]) && $argv[3] != $name) continue;
	$while = 0;
	do {
		if ($while++ == 3) {
			$echo .= " - Unable to read reports for '" . $name . "'";
			if ($exit == 0) $exit = 3;
			break;
		}
		if ($while > 1) sleep(1);
		$data = shell_exec($cli . " --xml '<get_tasks task_id=\"" . $id . "\" filter=\"apply_overrides=1\"/>'");
	} while (!$data);
	$data = preg_replace("/.*<get_tasks_response/s", "<get_tasks_response", $data);
	$xml = @simplexml_load_string($data)->task->last_report->report;
	if (!$xml) {
		if (!$data || !isset($argv[3])) continue;
		$echo .= " - No report for '" . $name . "'";
		if ($exit == 0) $exit = 3;
		continue;
	}
	$argv[2] = $age[$name] ?? $age['!'];
	if ($xml->severity < 0) {
		$echo .= " - Report of '" . $name . "' contains errors";
		$exit = 2;
	} elseif ($argv[2] && strtotime($xml->timestamp) + 86400 * $argv[2] < time()) {
		$echo .= " - Report of '" . $name . "' is older than " . $argv[2] . " days";
		$exit = 2;
	}
	$result['high'] += $xml->result_count->hole;
	$result['medium'] += $xml->result_count->warning;
	$result['low'] += $xml->result_count->info;
	$result['log'] += $xml->result_count->log;
}
if ($exit) {
	if ($exit == 2) echo "CRITICAL: ";
	if ($exit == 1) echo "WARNING: ";
	if ($exit == 3) echo "UNKNOWN: ";
	echo ltrim($echo, " -") . "\n";
	exit($exit);
}

if (isset($argv[5])) {
	if ($argv[4] < $argv[5]) [$argv[4], $argv[5]] = [$argv[5], $argv[4]];
	if ($xml->severity >= $argv[4]) {
		$echo = $xml->severity . " >= " . $argv[4] . " >= " . $argv[5] . " - ";
	} elseif ($xml->severity >= $argv[5]) {
		$echo = $xml->severity . " >= " . $argv[5] . " <= " . $argv[4] . " - ";
	} else {
		$echo = $xml->severity . " < " . $argv[5] . " <= " . $argv[4] . " - ";
	}
} elseif (isset($argv[4])) {
	$echo = $xml->severity . ($xml->severity >= $argv[4] ? " >= " : " < ") . $argv[4] . " - ";
} elseif (isset($argv[3])) {
	$echo = $xml->severity . " - ";
} else $echo = "";

$echo .= $result['high'] . " High, " . $result['medium'] . " Medium, " . $result['low'] . " Low, " . $result['log'] . " Log\n";
if (isset($argv[4])) $result = array("high" => $xml->severity >= $argv[4], "medium" => isset($argv[5]) && $xml->severity >= $argv[5]);

if ($result['high']) {
	echo "CRITICAL: " . $echo;
	exit(2);
} elseif ($result['medium']) {
	echo "WARNING: " . $echo;
	exit(1);
} else {
	echo "OK: " . $echo;
	exit(0);
}

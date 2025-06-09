#!/usr/bin/env php
<?php

#TrueNAS API -> see https://www.truenas.com/docs/api/scale_websocket_api.html

$filename = basename(__FILE__);
if (substr($argv[1] ?? "", -strlen($filename)) == $filename) array_shift($argv);
if (substr($argv[0] ?? "", -strlen($filename)) == $filename) array_shift($argv);
if (count($argv) != 2) {
	echo "USAGE: " . $filename . " TrueNAS APIKEY\n";
	exit(3);
}

$exit = 0;
$echo = array();
$levels = array("?", "INFO", "NOTICE", "WARNING", "ERROR", "CRITICAL", "ALERT", "EMERGENCY");

try {
	@include("vendor/autoload.php");
	$config = new WSSC\Components\ClientConfig();
	$config->setContextOptions(array("ssl" => array("verify_peer" => FALSE, "verify_peer_name" => FALSE)));
	$client = new WSSC\WebSocketClient("wss://" . $argv[0] . "/websocket", $config);
} catch (Throwable $throwable) {
	$error = $throwable->getMessage();
	echo "UNKNOWN: " . $error . "\n";
	if (preg_match("/Class .* not found/", $error)) echo "Please try 'composer install'...\n";
	exit(3);
} catch (Exception $exception) {
	$error = $exception->getMessage();
	echo "UNKNOWN: " . $error . "\n";
	exit(3);
}

$client->send(json_encode(array("msg" => "connect", "version" => "1", "support" => array("1"))));
$data = json_decode($client->receive(), TRUE);
if ($data['msg'] != "connected") {
	echo "UNKNOWN: Connection lost\n";
	exit(3);
}
$session = $data['session'];

$client->send(json_encode(array("id" => $session, "msg" => "method", "method" => "auth.login_with_api_key", "params" => array($argv[1]))));
$data = json_decode($client->receive(), TRUE);
if (!$data['result']) {
	echo "UNKNOWN: Login failed\n";
	exit(3);
}

$client->send(json_encode(array("id" => $session, "msg" => "method", "method" => "alert.list")));
$data = json_decode($client->receive(), TRUE);
if (!is_array($data['result'])) {
	echo "UNKNOWN: No data\n";
	exit(3);
}

$client->send(json_encode(array("id" => $session, "msg" => "method", "method" => "auth.logout")));
$client->receive();
$client->close();

foreach ($data['result'] as $alert) {
	if ($alert['dismissed']) continue;
	$level = intval(array_search(strtoupper($alert['level']), $levels));

	if ($level == 1 || $level == 2) continue;
	if ($level == 0 || $level == 3) {
		if ($exit == 0) $exit = 1;
	}
	if ($level >= 4) $exit = 2;

	$echo[$level . "-" . $alert['datetime']['$date'] . $alert['uuid']] = $levels[$level] . ": " . $alert['formatted'];
}

krsort($echo);
echo $exit == 0 ? "OK" : implode("\n", $echo) . "\n";
exit($exit);

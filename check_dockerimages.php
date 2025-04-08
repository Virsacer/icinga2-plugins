#!/usr/bin/env php
<?php

$filename = basename(__FILE__);
$options = getopt("hf:i:", array("ok"));
if (array_key_exists("h", $options)) {
	echo "USAGE: " . $filename . " [ -f ForceImage ] [ -i IgnoreImage ] [ --ok ]\n";
	exit(3);
}

$containers = trim(shell_exec("docker ps -a --format '{{.Image}} {{.Names}}'"));
if (!$containers) {
	echo "UNKNOWN: No data\n";
	exit(3);
}

$exit = 0;
$echo = array();
$time = time();
$containers = explode("\n", $containers);

if (array_key_exists("i", $options)) {
	if (!is_array($options['i'])) $options['i'] = array($options['i']);
	foreach ($options['i'] as $ignore) {
		$containers = preg_grep("/^" . $ignore . "/", $containers, PREG_GREP_INVERT);
	}
}
if (array_key_exists("f", $options)) {
	if (!is_array($options['f'])) $options['f'] = array($options['f']);
	$containers = array_merge($containers, $options['f']);
}

foreach ($containers as $container) {
	$container = explode(" ", $container);

	$cache = __DIR__ . "/cache/" . $filename . "-" . str_replace("/", "-", $container[0]);
	if (!file_exists($cache) || $time - filemtime($cache) > 3 * 3600) {
		$manifest = shell_exec("docker manifest inspect -v '" . $container[0] . "' 2>&1");
		if (strpos($manifest, "handshake timeout") !== FALSE) $manifest = shell_exec("docker manifest inspect -v '" . $container[0] . "' 2>&1");
		if (strpos($manifest, "handshake timeout") !== FALSE) $manifest = shell_exec("docker manifest inspect -v '" . $container[0] . "' 2>&1");
		if ($manifest) file_put_contents($cache, $manifest);
	} else {
		$manifest = file_get_contents($cache);
	}

	if (!$manifest || strpos($manifest, "error") !== FALSE || strpos($manifest, "toomanyrequests") !== FALSE || strpos($manifest, "handshake timeout") !== FALSE) {
		$echo["2-" . $container[0]] = "[WARNING] " . ($container[1] ?? $container[0]) . ": No manifest for '" . $container[0] . "'";
		if ($exit == 0) $exit = 1;
		continue;
	}

	$image = shell_exec("docker inspect '" . ($container[1] ?? $container[0]) . "'|grep '\"" . (isset($container[1]) ? "Image" : "Id") . "\": \"sha256:'");
	$image = preg_replace("/.*sha256:([0-9a-f]+).*/s", "$1", $image);
	if (!isset($container[1])) $container[1] = $container[0];

	if (strpos($manifest, $image) === FALSE) {
		$echo["1-" . $container[0]] = "[CRITICAL] " . $container[1] . ": '" . $container[0] . "' does not match manifest";
		$exit = 2;
		continue;
	}

	if (isset($options['ok'])) $echo["3-" . $container[0]] = "[OK] " . $container[1] . ": '" . $container[0] . "' matches manifest";
}

ksort($echo);
$echo = "\n" . implode("\n", $echo) . "\n";

if ($exit == 2) {
	echo "CRITICAL:" . $echo;
} elseif ($exit == 1) {
	echo "WARNING:" . $echo;
} else {
	echo "OK:" . $echo;
}
exit($exit);

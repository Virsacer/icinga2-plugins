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
	echo "UNKNOWN - No data";
	exit(3);
}

$out = array();
$status = 0;
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
	if (!isset($container[1])) $container[1] = $container[0];

	$cache = __DIR__ . "/cache/" . $filename . "-" . str_replace("/", "-", $container[0]);
	if (!file_exists($cache) || $time - filemtime($cache) > 3 * 3600) {
		$manifest = shell_exec("docker manifest inspect -v '" . $container[0] . "' 2>&1");
		if ($manifest) file_put_contents($cache, $manifest);
	} else {
		$manifest = file_get_contents($cache);
	}

	if (!$manifest || strpos($manifest, "error") !== FALSE) {
		if ($status == 0) $status = 1;
		$out["2-" . $container[0]] = "[WARNING] " . $container[1] . ": No manifest for '" . $container[0] . "'";
		continue;
	}

	$image = shell_exec("docker inspect '" . $container[1] . "'|grep '\"Id\": \"sha256:'");
	$image = preg_replace("/.*sha256:([0-9a-f]+).*/s", "$1", $image);

	if (strpos($manifest, $image) === FALSE) {
		$status = 2;
		$out["1-" . $container[0]] = "[CRITICAL] " . $container[1] . ": '" . $container[0] . "' does not match manifest";
		continue;
	}

	if (isset($options['ok'])) $out["3-" . $container[0]] = "[OK] " . $container[1] . ": '" . $container[0] . "' matches manifest";
}

ksort($out);
$out = "\n" . implode("\n", $out);

if ($status == 2) {
	echo "CRITICAL" . $out;
} elseif ($status == 1) {
	echo "WARNING" . $out;
} else {
	echo "OK" . $out;
}
exit($status);

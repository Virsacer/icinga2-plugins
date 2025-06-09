#!/usr/bin/env php
<?php

$filename = basename(__FILE__);
if (substr($argv[1] ?? "", -strlen($filename)) == $filename) array_shift($argv);
if (substr($argv[0] ?? "", -strlen($filename)) == $filename) array_shift($argv);
if (count($argv) != 3) {
	echo "USAGE: " . $filename . " iDRAC Username Password\n";
	exit(3);
}

$exit = 0;
$echo = "";
$cli = "/opt/dell/srvadmin/bin/idracadm7 -r " . $argv[0] . " -u " . $argv[1] . " -p " . $argv[2] . " --nocertwarn ";

$data = trim(shell_exec($cli . "get System.LCD 2>&1"));
if (strpos($data, "Unable to connect") !== FALSE) {
	echo rtrim($data, "\r\n") . "\n";
	exit(2);
} elseif (strpos($data, "ERROR") !== FALSE || strpos($data, "Login failed") !== FALSE) {
	echo rtrim($data, "\r\n") . "\n";
	exit(3);
}

preg_match_all("/#?([^=]*)=([^\n]*)\n/", $data, $display);
$display = array_combine($display[1], $display[2]);

$data = trim(shell_exec($cli . "getsysinfo -s"));
if (strpos($data, "Unable to connect") !== FALSE) {
	echo "UNKNOWN: Connection lost\n";
	exit(3);
}

$data = preg_replace("/.*System Information:\s*/s", "\n", $data);
$data = preg_replace("/\s*Embedded NIC MAC.*/s", "\n\n", $data);

preg_match("/Service Tag.*= ([^\n]*)\n/", $data, $servicetag);
$servicetag = $servicetag[1];

if (isset($display['NumberErrsVisible'])) {
	if ($display['NumberErrsVisible'] > 0) {
		$echo .= " " . trim($display['CurrentDisplay']);
		$exit = 1;
	}
} elseif (trim($display['Configuration']) == "User Defined") {
	if ($display['CurrentDisplay'] != $display['UserDefinedString']) {
		$echo .= " " . trim($display['CurrentDisplay']);
		$exit = 1;
	}
} elseif (trim($display['Configuration']) == "Service Tag") {
	if ($display['CurrentDisplay'] != $servicetag) {
		$echo .= " " . trim($display['CurrentDisplay']);
		$exit = 1;
	}
} elseif (preg_match("/[A-Z]{3,4}[0-9]{4} /", $display['CurrentDisplay'])) {
	$echo .= " " . trim($display['CurrentDisplay']);
	$exit = 1;
}

$data .= trim(shell_exec($cli . "getsysinfo -d -c -w -t"));
if (strpos($data, "Unable to connect") !== FALSE) {
	echo "UNKNOWN: Connection lost\n";
	exit(3);
}

$data = preg_replace("/RAC Date\/Time[^\n]*\s*/", "", $data);
$data = preg_replace("/ " . $servicetag . "/", " <a href='https://www.dell.com/support/home/product-support/servicetag/" . $servicetag . "/overview'>" . $servicetag . "</a>", $data);
$echo .= str_replace("\n", "<br/>", "\n" . ltrim($data, "\n"));

preg_match("/System Model.*?PowerEdge ([^\n]*)\n/", $data, $model);
$model = $model[1];
$images = array(
	"R610" => "https://i.dell.com/is/image/DellContent/content/dam/global-site-design/product_images/dell_enterprise_products/enterprise_systems/poweredge/poweredge_r610/best_of/server-poweredge-r610-bestof-500.png?fmt=png-alpha",
	"R620" => "https://i.dell.com/is/image/DellContent/content/dam/global-site-design/product_images/dell_enterprise_products/enterprise_systems/poweredge/poweredge_r620/best_of/server-poweredge-r620-left-bestof-500.psd?fmt=png-alpha",
	"R720" => "https://i.dell.com/is/image/DellContent/content/dam/global-site-design/product_images/dell_enterprise_products/enterprise_systems/poweredge/poweredge_r720/best_of/server-poweredge-r720-right-bestof-500.psd?fmt=png-alpha",
	"R730" => "https://i.dell.com/is/image/DellContent/content/dam/global-site-design/product_images/dell_enterprise_products/enterprise_systems/poweredge/poweredge_r730/global_spi/server-poweredge-r730-left-bestof-500-ng-v2.psd?fmt=png-alpha",
	"R930" => "https://i.dell.com/is/image/DellContent/content/dam/global-site-design/product_images/dell_enterprise_products/enterprise_systems/poweredge/poweredge_r930/global_spi/server-poweredge-r930-left-bestof-500-ng.psd?fmt=png-alpha",
	"R740" => "https://i.dell.com/is/image/DellContent/content/dam/global-asset-library/products/enterprise_servers/poweredge/r740/dellemc_per740_24x25_2_lf.psd?wid=500&fmt=png-alpha",
	"R740xd" => "https://i.dell.com/is/image/DellContent/content/dam/global-asset-library/products/enterprise_servers/poweredge/r740xd/dellemc_per740xd_24x25_bezel_2_lf.psd?wid=500&fmt=png-alpha",
	"R940" => "https://i.dell.com/is/image/DellContent/content/dam/global-asset-library/products/enterprise_servers/poweredge/r940/dellemc_per940_24x2_5_bezel_lcd_lf.psd?wid=500&fmt=png-alpha",
	"R750" => "https://i.dell.com/is/image/DellContent/content/dam/global-site-design/product_images/dell_enterprise_products/enterprise_systems/poweredge/r_series/r750/global_spi/ng/enterprise-servers-poweredge-r750-lf-bestof-500-ng.psd?fmt=png-alpha",
	"R6515" => "https://i.dell.com/is/image/DellContent/content/dam/global-asset-library/products/enterprise_servers/poweredge/r6515/dellemc_per6515_10x25_emc-lcd-bezel_lf.psd?wid=500&fmt=png-alpha",
	"R7515" => "https://i.dell.com/is/image/DellContent/content/dam/global-site-design/product_images/dell_enterprise_products/enterprise_systems/poweredge/c6525/global_spi/ng/enterprise-server-poweredge-r7515-lf-bestof-500-ng.psd?fmt=png-alpha",
	"R7525" => "https://i.dell.com/is/image/DellContent/content/dam/global-site-design/product_images/dell_enterprise_products/enterprise_systems/poweredge/poweredge_r7525/global_spi/ng/enterprise-servers-poweredge-r7525-lf-bestof-500-ng.psd?fmt=png-alpha",
);
if (file_exists("/usr/share/icingaweb2/public/img/PowerEdge/" . $model . ".png")) $images[$model] = "img/PowerEdge/" . $model . ".png";
if (array_key_exists($model, $images)) $echo .= "<br/><br/><div class='markdown'><img src='" . $images[$model] . "' alt='" . $model . "'/></div>";
$echo .= "\n";

if ($exit == 2) {
	echo "CRITICAL:" . $echo;
} elseif ($exit == 1) {
	echo "WARNING:" . $echo;
} else {
	echo "OK:" . $echo;
}
exit($exit);

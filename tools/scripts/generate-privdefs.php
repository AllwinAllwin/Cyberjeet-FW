#!/usr/local/bin/php -f
<?php
/*
 * generate-privdefs.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
 * This utility processes the <prefix>/usr/local/www
 * directory and builds a privilege definition file
 * based on the embedded metadata tags. For more info
 * please see <prefix>/etc/inc/meta.inc
 */

if (count($argv) < 2) {
	echo "usage: generate-privdefs <prefix>\n";
	echo "\n";
	echo "This utility generates privilege definitions and writes them to\n";
	echo "'<prefix>/etc/inc/priv.defs.inc'. The <prefix> parameter should\n";
	echo "be specified as your base pfSense working directory.\n";
	echo "\n";
	echo "Examples:\n";
	echo "#generate-privdefs /\n";
	echo "#generate-privdefs /home/pfsense/src/\n";
	echo "\n";
	exit -1;
}

$prefix = $argv[1];
if (!file_exists($prefix)) {
	echo "prefix {$prefix} is invalid";
	exit -1;
}

$metainc = $prefix."etc/inc/meta.inc";

if (!file_exists($metainc)) {
	echo "unable to locate {$metainc} file\n";
	exit -1;
}

require_once($metainc);

echo "--Locating www php files--\n";

$path = $prefix."/usr/local/www";
list_phpfiles($path, $found);

echo "--Gathering privilege metadata--\n";

$data;
sort($found);
foreach ($found as $fname)
	read_file_metadata($path."/".$fname, $data, "PRIV");

echo "--Generating privilege definitions--\n";
$privdef = $prefix."etc/inc/priv.defs.inc";

$fp = fopen($privdef, "w");
if (!$fp) {
	echo "unable to open {$privdef}\n";
	exit -2;
}

$pdata;
$pdata  = "<?php\n";
$pdata .= "/*\n";
$pdata .= " * priv.defs.inc - Default Privilege Definitions\n";
$pdata .= " * Generated by pfSense/tools/scripts/generate-privdefs.php\n";
$pdata .= " *\n";
$pdata .= " * ***************************************************\n";
$pdata .= " * DO NOT EDIT THIS FILE. IT IS GENERATED BY A SCRIPT.\n";
$pdata .= " * ***************************************************\n";
$pdata .= " *\n";
$pdata .= " * Text is pulled from metadata headers in the referenced files.\n";
$pdata .= " *\n";
$pdata .= " */\n";
$pdata .= "\n";
$pdata .= "\$priv_list = array();\n";
$pdata .= "\n";
$pdata .= "\$priv_list['page-all'] = array();\n";
$pdata .= "\$priv_list['page-all']['name'] = gettext(\"WebCfg - All pages\");\n";
$pdata .= "\$priv_list['page-all']['descr'] = gettext(\"Allow access to all pages\");\n";
$pdata .= "\$priv_list['page-all']['warn'] = \"standard-warning-root\";\n";
$pdata .= "\$priv_list['page-all']['match'] = array();\n";
$pdata .= "\$priv_list['page-all']['match'][] = \"*\";\n";
$pdata .= "\n";

foreach ($data as $fname => $tags) {

	foreach ($tags as $tname => $vals) {

		$ident = "";
		$name = "";
		$descr = "";
		$warn = "";
		$match = array();

		foreach ($vals as $vname => $vlist) {

			switch ($vname) {
				case "IDENT":
					$ident = $vlist[0];
					break;
				case "NAME":
					$name = $vlist[0];
					break;
				case "DESCR":
					$descr = $vlist[0];
					break;
				case "WARN":
					$warn = $vlist[0];
					break;
				case "MATCH":
					$match = $vlist;
					break;
			}
		}

		if (!$ident) {
			echo "invalid IDENT in {$fname} privilege\n";
			continue;
		}

		if (!count($match)) {
			echo "invalid MATCH in {$fname} privilege\n";
			continue;
		}

		$pdata .= "\$priv_list['{$ident}'] = array();\n";
		$pdata .= "\$priv_list['{$ident}']['name'] = gettext(\"WebCfg - {$name}\");\n";
		$pdata .= "\$priv_list['{$ident}']['descr'] = gettext(\"{$descr}\");\n";

		if (strlen($warn) > 0) {
			$pdata .= "\$priv_list['{$ident}']['warn'] = \"{$warn}\";\n";
		}

		$pdata .= "\$priv_list['{$ident}']['match'] = array();\n";

		foreach ($match as $url)
			$pdata .= "\$priv_list['{$ident}']['match'][] = \"{$url}\";\n";

		$pdata .= "\n";
	}
}

$pdata .= "\n";
$pdata .= "\$priv_rmvd = array();\n";
$pdata .= "\n";

$pdata .= "?>\n";
fwrite($fp, $pdata);

fclose($fp);

/*
 * TODO : Build additional functionality
 *

echo "--Checking for pages without privilege definitions--\n";

foreach ($found as $fname) {
	$match = false;
	foreach ($pages_current as $pname => $pdesc) {
		if (!strcmp($pname, $fname)) {
			$match = true;
			break;
		}
	}
	if (!$match)
		echo "missing: $fname\n";
}

echo "--Checking for stale privilege definitions--\n";

foreach ($pages_current as $pname => $pdesc) {
	$match = false;
	foreach ($found as $fname) {
		if (!strncmp($fname, $pname, strlen($fname))) {
			$match = true;
			break;
		}
	}
	if (!$match)
		echo "stale: $pname\n";
}

 */

?>

<?php
// the hub operations in plugin.php use Network unqualified: the import must stay
$s = file_get_contents(dirname(__DIR__) . "/core/plugins/missions/plugin.php");
preg_match_all("/(?<![\\\w])Network::/", $s, $m); $uses = (bool)preg_match("/^use SysferaMissions\\\\\{[^}]*Network/m", $s);
echo (!$m[0] || $uses ? "  ok   " : "  FAIL ") . "plugin.php imports Network when it uses it\n";
exit(!$m[0] || $uses ? 0 : 1);

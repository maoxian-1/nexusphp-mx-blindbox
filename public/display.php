<?php
require_once("../../../include/bittorrent.php");
dbconn();

// 获取当前用户
if (isset($CURUSER)) {
    echo "<!-- User logged in: " . $CURUSER['username'] . " -->\n";
} else {
    echo "<!-- User not logged in -->\n";
}

// 测试盲盒模块
$extraModules = [];
$extraModules = apply_filter('nexus_home_module', $extraModules);

echo "<!-- Modules count: " . count($extraModules) . " -->\n";

// 输出所有模块
foreach ($extraModules as $module) {
    echo $module;
}
?>

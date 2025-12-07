<?php
// 盲盒插件公共入口文件
// 根据请求路径分发到对应的处理文件

$path = $_SERVER['PATH_INFO'] ?? $_GET['path'] ?? '';

switch ($path) {
    case '/admin':
        require_once __DIR__ . '/admin.php';
        break;
    case '/api':
        require_once __DIR__ . '/api.php';
        break;
    default:
        // 默认显示盲盒测试页面
        require_once __DIR__ . '/display.php';
        break;
}
?>

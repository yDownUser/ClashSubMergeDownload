<?php
// 检查是否已经发送了头部
if (!headers_sent()) {
    // 如果没有发送头部，则启用字符编码声明
    header('Content-Type: text/html; charset=utf-8');
}

define('CACHE_DIR', 'cache');
define('DATA_PATH', CACHE_DIR . DIRECTORY_SEPARATOR . 'data');
define('DOWN_PATH', CACHE_DIR . DIRECTORY_SEPARATOR . 'down');
define('LOG_PATH', 'tmp');

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'fun.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'clash.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

$cur_data =  date('Ymd');

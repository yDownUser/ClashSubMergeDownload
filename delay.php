<?php
header("Content-type: text/html; charset=utf-8");

date_default_timezone_set('Asia/Shanghai');

echo date('Y-m-d H:i:s') . "\n";
sleep(15);
echo date('Y-m-d H:i:s') . "\n";

$mode = "delay";

require_once('csv.php');

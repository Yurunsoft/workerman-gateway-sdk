<?php

use GatewayWorker\Register;
use Workerman\Worker;

// 自动加载类
require_once dirname(__DIR__, 2).'/vendor/autoload.php';

// register 必须是text协议
$register = new Register('text://0.0.0.0:1238');

// 如果不是在根目录启动，则运行runAll方法
if (! defined('GLOBAL_START')) {
    Worker::runAll();
}

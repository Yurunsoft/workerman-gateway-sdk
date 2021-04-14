<?php

// 自动加载类
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

ini_set('display_errors', 'on');
use Workerman\Worker;

if (0 === strpos(strtolower(\PHP_OS), 'win'))
{
    exit("start.php not support windows, please use start_for_win.bat\n");
}

// 检查扩展
if (!extension_loaded('pcntl'))
{
    exit("Please install pcntl extension. See http://doc3.workerman.net/appendices/install-extension.html\n");
}

if (!extension_loaded('posix'))
{
    exit("Please install posix extension. See http://doc3.workerman.net/appendices/install-extension.html\n");
}

// 标记是全局启动
define('GLOBAL_START', 1);

// 加载所有Applications/*/start.php，以便启动所有服务
foreach (glob(__DIR__ . '/worker_*.php') as $start_file)
{
    require_once $start_file;
}
// 运行所有服务
Worker::runAll();

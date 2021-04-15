# Workerman Gateway SDK

一个支持在 Swoole 或其它非 Workerman 环境，开发 Gateway Worker 的组件。

支持用 Workerman Gateway 做网关，Swoole 编写业务代码。

## 安装

`composer require yurunsoft/workerman-gateway-sdk`

## Demo

```php
<?php

use Workerman\Gateway\Config\GatewayWorkerConfig;
use Workerman\Gateway\Gateway\Contract\IGatewayClient;
use Workerman\Gateway\Gateway\GatewayWorkerClient;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = new GatewayWorkerConfig();
$config->setRegisterAddress('127.0.0.1:1238');

$client = new GatewayWorkerClient(mt_rand(), $config);
$client->onException = function (Throwable $th) {
    var_dump($th->getMessage());
};
$client->onGatewayMessage = function (IGatewayClient $client, array $message) {
    var_dump($message);
};
$client->run();
```

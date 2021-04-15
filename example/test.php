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

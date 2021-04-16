<?php

declare(strict_types=1);

use GatewayWorker\Lib\Context;
use GatewayWorker\Lib\Gateway;
use GatewayWorker\Protocols\GatewayProtocol;
use Swoole\Coroutine;
use function Swoole\Coroutine\parallel;
use Workerman\Gateway\Config\GatewayWorkerConfig;
use Workerman\Gateway\Gateway\Contract\IGatewayClient;
use Workerman\Gateway\Gateway\GatewayWorkerClient;

require dirname(__DIR__) . '/vendor/autoload.php';

Co\run(function () {
    // 一个进程里可以开多个协程去消费
    parallel(swoole_cpu_num(), function () {
        $config = new GatewayWorkerConfig();
        $config->setRegisterAddress('127.0.0.1:1238');

        // Gateway Client 配置
        Gateway::$registerAddress = $config->getRegisterAddress();

        $workerKey = getmypid() . '-' . Coroutine::getuid();
        // Gateway Worker
        $client = new GatewayWorkerClient($workerKey, $config);
        $client->onException = function (Throwable $th) {
            // 异常处理
            var_dump($th->getMessage(), $th->getTraceAsString());
        };
        $client->onGatewayMessage = function (IGatewayClient $client, array $message) {
            // 网关消息
            var_dump($message);
            $clientId = Context::addressToClientId($message['local_ip'], $message['local_port'], $message['connection_id']);
            switch ($message['cmd']) {
                case GatewayProtocol::CMD_ON_CONNECT:
                    // 连接
                    var_dump('connect:' . $clientId);
                    break;
                case GatewayProtocol::CMD_ON_MESSAGE:
                    var_dump('message:' . $clientId, 'body:' . $message['body']);
                    $data = json_decode($message['body'], true);
                    switch ($data['action'] ?? '')
                    {
                        case 'send':
                            // {"action":"send", "content":"test content"}
                            // 广播给所有用户
                            Gateway::sendToAll(json_encode([
                                'action'  => 'receive',
                                'content' => $data['content'] ?? '',
                            ]));
                            break;
                    }
                    break;
                case GatewayProtocol::CMD_ON_CLOSE:
                    var_dump('close:' . $clientId);
                    break;
                case GatewayProtocol::CMD_ON_WEBSOCKET_CONNECT:
                    var_dump('websocket message:' . $clientId, 'body:' . $message['body']);
                    break;
            }
        };
        $client->run();
    });
});

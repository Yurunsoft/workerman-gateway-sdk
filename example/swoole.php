<?php

declare(strict_types=1);

use GatewayWorker\Lib\Context;
use GatewayWorker\Lib\Gateway;
use GatewayWorker\Protocols\GatewayProtocol;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use function Swoole\Coroutine\parallel;
use Workerman\Gateway\Config\GatewayWorkerConfig;
use Workerman\Gateway\Gateway\Contract\IGatewayClient;
use Workerman\Gateway\Gateway\GatewayWorkerClient;

require dirname(__DIR__) . '/vendor/autoload.php';

Co\run(function () {
    $channel = new Channel(1024);

    Coroutine::create(function () use ($channel) {
        // 通过 Channel 实现单进程多协程任务处理
        parallel(swoole_cpu_num(), function () use ($channel) {
            while (true)
            {
                $result = $channel->pop();
                if (false === $result)
                {
                    break;
                }
                switch ($result['type'])
                {
                    case 'onException':
                        /** @var Throwable $th */
                        ['th' => $th] = $result['data'];
                        // 异常处理
                        var_dump($th->getMessage(), $th->getTraceAsString());
                        break;
                    case 'onGatewayMessage':
                        /** @var IGatewayClient $client */
                        ['client' => $client, 'message' => $message] = $result['data'];
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
                                var_dump('websocket connect:' . $clientId, 'body:', $message['body']);
                                break;
                        }
                        break;
                }
            }
        });
    });

    $config = new GatewayWorkerConfig();
    $config->setRegisterAddress('127.0.0.1:1238');

    // Gateway Client 配置
    Gateway::$registerAddress = $config->getRegisterAddress();

    $workerKey = getmypid() . '-' . Coroutine::getuid();
    // Gateway Worker
    $client = new GatewayWorkerClient($workerKey, $config);
    // 异常处理
    $client->onException = function (Throwable $th) use ($channel) {
        $channel->push([
            'type' => 'onException',
            'data' => [
                'th' => $th,
            ],
        ]);
    };
    // 网关消息
    $client->onGatewayMessage = function (IGatewayClient $client, array $message) use ($channel) {
        $channel->push([
            'type' => 'onGatewayMessage',
            'data' => [
                'client'  => $client,
                'message' => $message,
            ],
        ]);
    };
    $client->run();
});

<?php

declare(strict_types=1);

namespace Workerman\Gateway\Client\Gateway;

use GatewayWorker\Protocols\GatewayProtocol;
use Workerman\Gateway\Client\Config\GatewayClientConfig;
use Workerman\Gateway\Client\Gateway\Contract\IGatewayClient;
use Workerman\Gateway\Client\Socket\ISocket;

class GatewayClient implements IGatewayClient
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var GatewayClientConfig
     */
    protected $config;

    /**
     * @var ISocket
     */
    private $socket;

    public function __construct(string $host, int $port, ?GatewayClientConfig $config = null)
    {
        $this->host = $host;
        $this->port = $port;
        $config = ($this->config = $config ?? (new GatewayClientConfig()));
        $className = $config->getSocket();
        $this->socket = new $className($host, $port);
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getConfig(): GatewayClientConfig
    {
        return $this->config;
    }

    public function getSocket(): ISocket
    {
        return $this->socket;
    }

    public function connect(): void
    {
        $this->socket->connect();
    }

    public function close(): void
    {
        $this->socket->close();
    }

    public function send(array $data, ?float $timeout = null): int
    {
        return $this->socket->send(GatewayProtocol::encode($data), $timeout ?? $this->config->getSendTimeout());
    }

    public function recv(?float $timeout = null): array
    {
        $timeout = ($timeout ?? $this->config->getRecvTimeout());
        $head = $this->socket->recv(GatewayProtocol::HEAD_LEN, $timeout);
        $body = $this->socket->recv(GatewayProtocol::input($head), $timeout);

        return GatewayProtocol::decode($head . $body);
    }

    public function sendRecv(array $data, ?float $timeout = null): array
    {
        $this->send($data, $timeout);

        return $this->recv($timeout);
    }
}

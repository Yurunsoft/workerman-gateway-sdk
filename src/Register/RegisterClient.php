<?php

declare(strict_types=1);

namespace Workerman\Gateway\Register;

use Workerman\Gateway\Config\RegisterClientConfig;
use Workerman\Gateway\Exception\InvalidResponseException;
use Workerman\Gateway\Register\Contract\IRegisterClient;
use Workerman\Gateway\Socket\ISocket;

class RegisterClient implements IRegisterClient
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
     * @var RegisterClientConfig
     */
    protected $config;

    /**
     * @var ISocket
     */
    private $socket;

    public function __construct(string $host, int $port, ?RegisterClientConfig $config = null)
    {
        $this->host = $host;
        $this->port = $port;
        $config = ($this->config = $config ?? (new RegisterClientConfig()));
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

    public function getConfig(): RegisterClientConfig
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

    public function send(string $event, array $data = [], ?float $timeout = null): int
    {
        $data['event'] = $event;
        $data['secret_key'] = $this->config->getSecretKey();

        return $this->socket->send(json_encode($data) . "\n", $timeout ?? $this->config->getSendTimeout());
    }

    public function recv(?float $timeout = null): array
    {
        $data = $this->socket->recvLine(null, $timeout ?? $this->config->getRecvTimeout());

        return json_decode($data, true);
    }

    public function isConnected(): bool
    {
        return $this->socket->isConnected();
    }

    /**
     * @param mixed $result
     */
    public function isReceiveable(?float $timeout = null, &$result = null): bool
    {
        return $this->socket->isReceiveable($timeout, $result);
    }

    /**
     * @param mixed $result
     */
    public function isWriteable(?float $timeout = null, &$result = null): bool
    {
        return $this->socket->isWriteable($timeout, $result);
    }

    public function ping(): void
    {
        $this->send('ping');
    }

    public function getAllGatewayAddresses(): array
    {
        $this->send('worker_connect');
        $data = $this->recv();
        $addresses = $data['addresses'] ?? null;
        if (!\is_array($addresses))
        {
            throw new InvalidResponseException(sprintf('Gateway::getAllGatewayAddressesFromRegister() with registerAddress:%s:%s return %s', $this->getHost(), $this->getPort(), var_export($data, true)));
        }

        return $addresses;
    }

    public function workerConnect(): void
    {
        $this->send('worker_connect');
    }
}

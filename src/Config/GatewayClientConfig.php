<?php

declare(strict_types=1);

namespace Workerman\Gateway\Client\Config;

use Workerman\Gateway\Client\Gateway\GatewayClient;
use Workerman\Gateway\Client\Register\RegisterClient;
use Workerman\Gateway\Client\Socket\StreamSocket;

class GatewayClientConfig extends SocketConfig
{
    /**
     * @var string[]
     */
    protected $registerAddress;

    /**
     * @var string
     */
    protected $secretKey = '';

    /**
     * @var string
     */
    protected $register = RegisterClient::class;

    /**
     * @var string
     */
    protected $client = GatewayClient::class;

    /**
     * @var string
     */
    protected $socket = StreamSocket::class;

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function setSecretKey(string $secretKey): self
    {
        $this->secretKey = $secretKey;

        return $this;
    }

    public function getRegister(): string
    {
        return $this->register;
    }

    public function setRegister(string $register): self
    {
        $this->register = $register;

        return $this;
    }

    public function getClient(): string
    {
        return $this->client;
    }

    public function setClient(string $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getSocket(): string
    {
        return $this->socket;
    }

    public function setSocket(string $socket): self
    {
        $this->socket = $socket;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getRegisterAddress(): array
    {
        return $this->registerAddress;
    }

    /**
     * @param string|string[] $registerAddress
     */
    public function setRegisterAddress($registerAddress): self
    {
        $this->registerAddress = (array) $registerAddress;

        return $this;
    }
}

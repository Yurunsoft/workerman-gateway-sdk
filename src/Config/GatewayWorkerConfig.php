<?php

declare(strict_types=1);

namespace Workerman\Gateway\Config;

class GatewayWorkerConfig extends GatewayClientConfig
{
    /**
     * @var int
     */
    protected $pingInterval = 25;

    /**
     * @var int
     */
    protected $reconnectInterval = 3;

    public function getPingInterval(): int
    {
        return $this->pingInterval;
    }

    public function setPingInterval(int $pingInterval): self
    {
        $this->pingInterval = $pingInterval;

        return $this;
    }

    public function getReconnectInterval(): int
    {
        return $this->reconnectInterval;
    }

    public function setReconnectInterval(int $reconnectInterval): self
    {
        $this->reconnectInterval = $reconnectInterval;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Workerman\Gateway\Config;

class GatewayWorkerConfig extends GatewayClientConfig
{
    /**
     * @var float
     */
    protected $pingInternal = 25;

    public function getPingInternal(): float
    {
        return $this->pingInternal;
    }

    public function setPingInternal(float $pingInternal): self
    {
        $this->pingInternal = $pingInternal;

        return $this;
    }
}

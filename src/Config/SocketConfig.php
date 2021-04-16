<?php

declare(strict_types=1);

namespace Workerman\Gateway\Config;

class SocketConfig extends AbstractConfig
{
    /**
     * @var int
     */
    private $connectTimeout = 3;

    /**
     * @var float
     */
    protected $sendTimeout = -1;

    /**
     * @var float
     */
    protected $recvTimeout = -1;

    /**
     * @var int
     */
    protected $maxWriteAttempts = 3;

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function setConnectTimeout(int $connectTimeout): self
    {
        $this->connectTimeout = $connectTimeout;

        return $this;
    }

    public function getMaxWriteAttempts(): int
    {
        return $this->maxWriteAttempts;
    }

    public function setMaxWriteAttempts(int $maxWriteAttempts): self
    {
        $this->maxWriteAttempts = $maxWriteAttempts;

        return $this;
    }

    public function setSendTimeout(float $value): self
    {
        $this->sendTimeout = $value;

        return $this;
    }

    public function getSendTimeout(): float
    {
        return $this->sendTimeout;
    }

    public function getRecvTimeout(): float
    {
        return $this->recvTimeout;
    }

    public function setRecvTimeout(float $recvTimeout): self
    {
        $this->recvTimeout = $recvTimeout;

        return $this;
    }
}

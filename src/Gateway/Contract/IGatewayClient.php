<?php

declare(strict_types=1);

namespace Workerman\Gateway\Gateway\Contract;

use Workerman\Gateway\Config\GatewayClientConfig;
use Workerman\Gateway\Socket\ISocket;

interface IGatewayClient
{
    public function getHost(): string;

    public function getPort(): int;

    public function getConfig(): GatewayClientConfig;

    public function isConnected(): bool;

    public function getSocket(): ISocket;

    public function connect(): void;

    public function close(): void;

    public function send(array $data, ?float $timeout = null): int;

    public function recv(?float $timeout = null): array;

    public function sendRecv(array $data, ?float $timeout = null): array;

    /**
     * @param mixed $result
     */
    public function isReceiveable(?float $timeout = null, &$result = null): bool;

    /**
     * @param mixed $result
     */
    public function isWriteable(?float $timeout = null, &$result = null): bool;
}

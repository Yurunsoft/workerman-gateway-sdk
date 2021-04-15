<?php

declare(strict_types=1);

namespace Workerman\Gateway\Client\Gateway\Contract;

use Workerman\Gateway\Client\Config\GatewayClientConfig;
use Workerman\Gateway\Client\Socket\ISocket;

interface IGatewayClient
{
    public function getHost(): string;

    public function getPort(): int;

    public function getConfig(): GatewayClientConfig;

    public function getSocket(): ISocket;

    public function connect(): void;

    public function close(): void;

    public function send(array $data, ?float $timeout = null): int;

    public function recv(?float $timeout = null): array;

    public function sendRecv(array $data, ?float $timeout = null): array;
}

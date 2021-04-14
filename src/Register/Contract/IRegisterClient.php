<?php

declare(strict_types=1);

namespace Workerman\Gateway\Client\Register\Contract;

use Workerman\Gateway\Client\Config\RegisterClientConfig;
use Workerman\Gateway\Client\Socket\ISocket;

interface IRegisterClient
{
    public function getHost(): string;

    public function getPort(): int;

    public function getConfig(): RegisterClientConfig;

    public function getSocket(): ISocket;

    public function connect(): void;

    public function close(): void;

    public function send(string $event, array $data = [], ?float $timeout = null): int;

    public function recv(?float $timeout = null): array;

    public function getAllGatewayAddresses(): array;
}

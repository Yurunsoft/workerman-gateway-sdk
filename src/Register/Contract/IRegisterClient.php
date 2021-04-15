<?php

declare(strict_types=1);

namespace Workerman\Gateway\Register\Contract;

use Workerman\Gateway\Config\RegisterClientConfig;
use Workerman\Gateway\Socket\ISocket;

interface IRegisterClient
{
    public function getHost(): string;

    public function getPort(): int;

    public function getConfig(): RegisterClientConfig;

    public function isConnected(): bool;

    public function getSocket(): ISocket;

    public function connect(): void;

    public function close(): void;

    public function send(string $event, array $data = [], ?float $timeout = null): int;

    public function recv(?float $timeout = null): array;

    /**
     * @param mixed $result
     */
    public function isReceiveable(?float $timeout = null, &$result = null): bool;

    /**
     * @param mixed $result
     */
    public function isWriteable(?float $timeout = null, &$result = null): bool;

    public function ping(): void;

    public function getAllGatewayAddresses(): array;

    public function workerConnect(): void;
}

<?php

declare(strict_types=1);

namespace Workerman\Gateway\Client\Socket;

use Workerman\Gateway\Client\Config\SocketConfig;

interface ISocket
{
    public function __construct(string $host, int $port);

    public function getHost(): string;

    public function getPort(): int;

    public function getConfig(): SocketConfig;

    public function isConnected(): bool;

    public function connect(): void;

    public function close(): bool;

    public function send(string $data, ?float $timeout = null): int;

    public function recv(int $length, ?float $timeout = null): string;

    public function recvLine(?int $length = null, ?float $timeout = null): string;
}

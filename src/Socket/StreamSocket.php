<?php

declare(strict_types=1);

namespace Workerman\Gateway\Socket;

use Workerman\Gateway\Config\SocketConfig;
use Workerman\Gateway\Exception\ConnectionException;
use Workerman\Gateway\Exception\SocketException;

class StreamSocket implements ISocket
{
    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var resource|null
     */
    protected $socket;

    /**
     * @var SocketConfig
     */
    protected $config;

    public function __construct(string $host, int $port, ?SocketConfig $config = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->config = $config ?? (new SocketConfig());
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getConfig(): SocketConfig
    {
        return $this->config;
    }

    public function isConnected(): bool
    {
        return null !== $this->socket;
    }

    public function connect(): void
    {
        $uri = sprintf('tcp://%s:%s', $this->host, $this->port);
        $socket = stream_socket_client(
            $uri,
            $errno,
            $errstr,
            $this->config->getConnectTimeout(),
            \STREAM_CLIENT_CONNECT
        );

        if (!\is_resource($socket))
        {
            throw new ConnectionException(sprintf('Could not connect to %s (%s [%d])', $uri, $errstr, $errno));
        }
        $this->socket = $socket;
    }

    public function close(): bool
    {
        if (\is_resource($this->socket))
        {
            fclose($this->socket);
            $this->socket = null;

            return true;
        }
        else
        {
            return false;
        }
    }

    public function send(string $data, ?float $timeout = null): int
    {
        // fwrite to a socket may be partial, so loop until we
        // are done with the entire buffer
        $failedAttempts = 0;
        $bytesWritten = 0;

        $bytesToWrite = \strlen($data);

        if (null === $timeout)
        {
            $timeout = $this->config->getSendTimeout();
        }
        while ($bytesWritten < $bytesToWrite)
        {
            // wait for stream to become available for writing
            $writable = $this->select([$this->socket], $timeout, false);

            if (false === $writable)
            {
                throw new SocketException('Could not write ' . $bytesToWrite . ' bytes to stream');
            }

            if (0 === $writable)
            {
                $res = $this->getMetaData();
                if (!empty($res['timed_out']))
                {
                    throw new SocketException('Timed out writing ' . $bytesToWrite . ' bytes to stream after writing ' . $bytesWritten . ' bytes');
                }

                throw new SocketException('Could not write ' . $bytesToWrite . ' bytes to stream');
            }

            // write remaining buffer bytes to stream
            $wrote = fwrite($this->socket, substr($data, $bytesWritten));

            if (-1 === $wrote || false === $wrote)
            {
                throw new SocketException('Could not write ' . \strlen($data) . ' bytes to stream, completed writing only ' . $bytesWritten . ' bytes');
            }

            if (0 === $wrote)
            {
                // Increment the number of times we have failed
                ++$failedAttempts;

                if ($failedAttempts > $this->config->getMaxWriteAttempts())
                {
                    throw new SocketException('After ' . $failedAttempts . ' attempts could not write ' . \strlen($data) . ' bytes to stream, completed writing only ' . $bytesWritten . ' bytes');
                }
            }
            else
            {
                // If we wrote something, reset our failed attempt counter
                $failedAttempts = 0;
            }

            $bytesWritten += $wrote;
        }

        return $bytesWritten;
    }

    public function recv(int $length, ?float $timeout = null): string
    {
        if (null === $timeout)
        {
            $timeout = $this->config->getRecvTimeout();
        }
        $readable = $this->select([$this->socket], $timeout);

        if (false === $readable)
        {
            $this->close();
            throw new SocketException(sprintf('Could not read %d bytes from stream (not readable)', $length));
        }

        if (0 === $readable)
        { // select timeout
            $res = $this->getMetaData();

            if (!empty($res['timed_out']))
            {
                throw new SocketException(sprintf('Timed out reading %d bytes from stream', $length));
            }

            throw new SocketException(sprintf('Could not read %d bytes from stream (not readable)', $length));
        }

        $remainingBytes = $length;
        $data = $chunk = '';

        while ($remainingBytes > 0)
        {
            $chunk = fread($this->socket, $remainingBytes);

            if (false === $chunk || 0 === \strlen($chunk))
            {
                // Zero bytes because of EOF?
                if (feof($this->socket))
                {
                    $this->close();
                    throw new SocketException(sprintf('Unexpected EOF while reading %d bytes from stream (no data)', $length));
                }
                // Otherwise wait for bytes
                $readable = $this->select([$this->socket], $timeout);
                if (1 !== $readable)
                {
                    throw new SocketException(sprintf('Timed out while reading %d bytes from stream, %d bytes are still needed', $length, $remainingBytes));
                }

                continue; // attempt another read
            }

            $data .= $chunk;
            $remainingBytes -= \strlen($chunk);
        }

        return $data;
    }

    public function recvLine(?int $length = null, ?float $timeout = null): string
    {
        if (null === $timeout)
        {
            $timeout = $this->config->getRecvTimeout();
        }
        $readable = $this->select([$this->socket], $timeout);

        if (false === $readable)
        {
            $this->close();
            throw new SocketException(sprintf('Could not read %d bytes from stream (not readable)', $length));
        }

        if (0 === $readable)
        { // select timeout
            $res = $this->getMetaData();

            if (!empty($res['timed_out']))
            {
                throw new SocketException(sprintf('Timed out reading %d bytes from stream', $length));
            }

            throw new SocketException(sprintf('Could not read %d bytes from stream (not readable)', $length));
        }

        if (null === $length)
        {
            $data = fgets($this->socket);
        }
        else
        {
            $data = fgets($this->socket, $length);
        }
        if (false === $data || 0 === \strlen($data))
        {
            // Zero bytes because of EOF?
            if (feof($this->socket))
            {
                $this->close();
                throw new SocketException(sprintf('Unexpected EOF while reading %d bytes from stream (no data)', $length));
            }
            throw new SocketException('Recv line failed');
        }

        return $data;
    }

    /**
     * @param mixed $result
     */
    public function isReceiveable(?float $timeout = null, &$result = null): bool
    {
        if (!$this->isConnected())
        {
            return false;
        }
        if (null === $timeout)
        {
            $timeout = $this->config->getRecvTimeout();
        }
        $result = $this->select([$this->socket], $timeout, true);

        return $result > 0;
    }

    /**
     * @param mixed $result
     */
    public function isWriteable(?float $timeout = null, &$result = null): bool
    {
        if (!$this->isConnected())
        {
            return false;
        }
        if (null === $timeout)
        {
            $timeout = $this->config->getSendTimeout();
        }
        $result = $this->select([$this->socket], $timeout, false);

        return $result > 0;
    }

    /**
     * @return int|false
     */
    protected function select(array $sockets, float $timeout, bool $isRead = true)
    {
        $null = [];
        $timeoutSec = (int) $timeout;
        if ($timeoutSec < 0)
        {
            $timeoutSec = null;
        }
        $timeoutUsec = max((int) (1000000 * ($timeout - $timeoutSec)), 0);

        if ($isRead)
        {
            return stream_select($sockets, $null, $null, $timeoutSec, $timeoutUsec);
        }

        return stream_select($null, $sockets, $null, $timeoutSec, $timeoutUsec);
    }

    protected function getMetaData(): array
    {
        return stream_get_meta_data($this->socket);
    }
}

<?php

declare(strict_types=1);

namespace Workerman\Gateway\Gateway;

use GatewayWorker\Protocols\GatewayProtocol;
use Workerman\Gateway\Config\GatewayWorkerConfig;
use Workerman\Gateway\Config\RegisterClientConfig;
use Workerman\Gateway\Exception\ConnectionException;
use Workerman\Gateway\Gateway\Contract\IGatewayClient;
use Workerman\Gateway\Register\Contract\IRegisterClient;

class GatewayWorkerClient
{
    /**
     * @var string
     */
    protected $workerKey;

    /**
     * @var GatewayWorkerConfig
     */
    protected $config;

    /**
     * @var IRegisterClient|null
     */
    protected $registerClient;

    /**
     * @var IGatewayClient[]
     */
    protected $gatewayClients = [];

    /**
     * @var bool
     */
    protected $running = false;

    /**
     * @var array
     */
    protected $gatewayAddresses = [];

    /**
     * @var float
     */
    protected $lastPingTime = 0;

    /**
     * @var int
     */
    protected $gatewayLastRetryConnectTime = 0;

    /**
     * @var int
     */
    protected $registerLastRetryConnectTime = 0;

    /**
     * @var callable|null
     */
    public $onException;

    /**
     * @var callable|null
     */
    public $onGatewayMessage;

    public function __construct(string $workerKey, ?GatewayWorkerConfig $config = null)
    {
        $this->workerKey = $workerKey;
        $this->config = $config ?? (new GatewayWorkerConfig());
    }

    public function getWorkerKey(): string
    {
        return $this->workerKey;
    }

    public function getConfig(): GatewayWorkerConfig
    {
        return $this->config;
    }

    protected function onRegisterMessage(IRegisterClient $client, array $message): void
    {
        if (!isset($message['event']))
        {
            throw new \RuntimeException('Received bad data from Register');
        }
        $event = $message['event'];
        switch ($event) {
            case 'broadcast_addresses':
                if (!\is_array($message['addresses']))
                {
                    throw new \RuntimeException('Received bad data from Register. Addresses empty');
                }
                $addresses = $message['addresses'];
                $gatewayAddresses = [];
                foreach ($addresses as $addr)
                {
                    $gatewayAddresses[$addr] = $addr;
                }
                $this->gatewayAddresses = $gatewayAddresses;
                foreach ($this->gatewayClients as $key => $client)
                {
                    if (!isset($gatewayAddresses[$key]))
                    {
                        $client->close();
                        unset($this->gatewayClients[$key]);
                    }
                }
                $config = $this->config;
                $gatewayClientClass = $config->getClient();
                foreach ($addresses as $address)
                {
                    if (!isset($this->gatewayClients[$address]))
                    {
                        [$host, $port] = explode(':', $address, 2);
                        /** @var IGatewayClient $client */
                        $client = $this->gatewayClients[$address] = new $gatewayClientClass($host, (int) $port, $config);
                        try
                        {
                            $client->connect();
                            $data = GatewayProtocol::$empty;
                            $data['cmd'] = GatewayProtocol::CMD_WORKER_CONNECT;
                            $data['body'] = json_encode([
                                'worker_key' => $this->getWorkerKey(),
                                'secret_key' => $config->getSecretKey(),
                            ]);
                            $client->send($data);
                        }
                        catch (ConnectionException $ce)
                        {
                        }
                        catch (\Throwable $th)
                        {
                            $this->onException($th);
                        }
                    }
                }
                break;
            default:
                throw new \RuntimeException("Receive bad event:$event from Register.");
        }
    }

    protected function recvRegisterClient(): int
    {
        $success = 0;
        $config = $this->config;
        if (null === $this->registerClient)
        {
            $className = $config->getRegister();
            $registerClientConfig = new RegisterClientConfig();
            $registerClientConfig->setSecretKey($config->getSecretKey());
            $registerClientConfig->setSocket($config->getSocket());
            $registerAddress = $config->getRegisterAddress();
            [$host, $port] = explode(':', $registerAddress[array_rand($registerAddress)], 2);
            /** @var IRegisterClient $client */
            $client = $this->registerClient = new $className($host, (int) $port, $registerClientConfig);
        }
        else
        {
            $client = $this->registerClient;
        }
        try
        {
            if (!$client->isConnected())
            {
                $time = time();
                if ($time - $this->registerLastRetryConnectTime < $config->getReconnectInterval())
                {
                    return $success;
                }
                $this->registerLastRetryConnectTime = $time;
                $client->connect();
                $client->workerConnect();
            }
            $result = null;
            if ($client->isReceiveable(0.001, $result))
            {
                $message = $client->recv();
                $this->onRegisterMessage($client, $message);
            }
            elseif (false === $result)
            {
                $client->close();
            }
            ++$success;
        }
        catch (ConnectionException $ce)
        {
            $this->registerClient = null;
        }
        catch (\Throwable $th)
        {
            $this->onException($th);
        }

        return $success;
    }

    protected function onGatewayMessage(IGatewayClient $client, array $message): void
    {
        if ($this->onGatewayMessage)
        {
            ($this->onGatewayMessage)($client, $message);
        }
    }

    protected function recvGatewayClients(): int
    {
        $success = 0;
        $config = $this->config;
        foreach ($this->gatewayClients as $client)
        {
            try
            {
                if (!$client->isConnected())
                {
                    $time = time();
                    if ($time - $this->gatewayLastRetryConnectTime < $config->getReconnectInterval())
                    {
                        continue;
                    }
                    $this->gatewayLastRetryConnectTime = $time;
                    $client->connect();
                    $data = GatewayProtocol::$empty;
                    $data['cmd'] = GatewayProtocol::CMD_WORKER_CONNECT;
                    $data['body'] = json_encode([
                        'worker_key' => $this->getWorkerKey(),
                        'secret_key' => $this->config->getSecretKey(),
                    ]);
                    $client->send($data);
                }
                $result = null;
                if ($client->isReceiveable(0.001, $result))
                {
                    $message = $client->recv();
                    $this->onGatewayMessage($client, $message);
                    ++$success;
                }
                elseif (false === $result)
                {
                    $client->close();
                    $client->connect();
                }
            }
            catch (ConnectionException $ce)
            {
            }
            catch (\Throwable $th)
            {
                $this->onException($th);
            }
        }

        return $success;
    }

    protected function onException(\Throwable $th): void
    {
        if ($this->onException)
        {
            ($this->onException)($th);
        }
    }

    protected function parsePing(): void
    {
        $time = microtime(true);
        if ($time - $this->lastPingTime > $this->config->getPingInterval())
        {
            try
            {
                $registerClient = $this->registerClient;
                if ($registerClient && $registerClient->isConnected())
                {
                    $registerClient->ping();
                }
            }
            catch (ConnectionException $ce)
            {
            }
            catch (\Throwable $th)
            {
                $this->onException($th);
            }
            $this->lastPingTime = $time;
        }
    }

    public function run(): void
    {
        $this->running = true;
        while ($this->running)
        {
            $count = 0;
            try
            {
                $count += $this->recvRegisterClient();
                $count += $this->recvGatewayClients();
                $this->parsePing();
            }
            catch (ConnectionException $ce)
            {
            }
            catch (\Throwable $th)
            {
                if ($this->onException)
                {
                    ($this->onException)($th);
                }
            }
            if (0 === $count)
            {
                usleep(1000);
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }
}

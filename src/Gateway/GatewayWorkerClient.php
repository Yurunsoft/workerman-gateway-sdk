<?php

declare(strict_types=1);

namespace Workerman\Gateway\Gateway;

use GatewayWorker\Protocols\GatewayProtocol;
use Workerman\Gateway\Config\GatewayWorkerConfig;
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
     * @var IRegisterClient[]
     */
    protected $registerClients = [];

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

    protected function connectRegisters(): void
    {
        $config = $this->config;
        $class = $config->getRegister();
        foreach ($config->getRegisterAddress() as $address)
        {
            [$host, $port] = explode(':', $address, 2);
            /** @var IRegisterClient $client */
            $client = $this->registerClients[$address] = new $class($host, (int) $port);
            try
            {
                $client->connect();
                $client->workerConnect();
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

    protected function recvRegisterClient(): void
    {
        foreach ($this->registerClients as $client)
        {
            try
            {
                if (!$client->isConnected())
                {
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
    }

    protected function onGatewayMessage(IGatewayClient $client, array $message): void
    {
        if ($this->onGatewayMessage)
        {
            ($this->onGatewayMessage)($client, $message);
        }
    }

    protected function recvGatewayClients(): void
    {
        foreach ($this->gatewayClients as $client)
        {
            try
            {
                if (!$client->isConnected())
                {
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
        if ($time - $this->lastPingTime > $this->config->getPingInternal())
        {
            foreach ($this->registerClients as $client)
            {
                try
                {
                    if ($client->isConnected())
                    {
                        $client->ping();
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
            $this->lastPingTime = $time;
        }
    }

    public function run(): void
    {
        $this->connectRegisters();
        $this->running = true;
        while ($this->running)
        {
            try
            {
                $this->recvRegisterClient();
                $this->recvGatewayClients();
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
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }
}

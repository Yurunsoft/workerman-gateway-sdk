<?php

use PHPUnit\Framework\TestCase;
use Workerman\Gateway\Register\Contract\IRegisterClient;
use Workerman\Gateway\Register\RegisterClient;

class RegisterClientTest extends TestCase
{
    public function testConnect(): IRegisterClient
    {
        $client = new RegisterClient('127.0.0.1', 1238);
        $client->connect();
        $this->assertTrue(true);

        return $client;
    }

    /**
     * @depends testConnect
     */
    public function testGetAllGatewayAddresses(IRegisterClient $client): void
    {
        $this->assertEquals([
            '127.0.0.1:2900',
        ], $client->getAllGatewayAddresses());
    }
}

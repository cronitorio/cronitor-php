<?php

use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = new Cronitor\Client($apiKey, $apiVersion, $environment);
    }

    public function testIsInitializable()
    {
        $apiKey = '1234';
        $apiVersion = '2020-01-01';
        $environment = 'staging';

        $this->client = new Cronitor\Client($apiKey, $apiVersion, $environment);
        $this->assertEquals($this->client->apiKey, $apiKey);
        $this->assertEquals($this->client->apiVersion, $apiVersion);
        $this->assertEquals($this->client->environment, $environment);
    }

    public function testMonitor()
    {
        $monitorKey = '1234';
        $monitor = $this->client->monitor($monitorKey);
        $this->assertInstanceOf(Cronitor\Monitor::class, $monitor);
    }
}

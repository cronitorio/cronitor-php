<?php

namespace Cronitor\Tests;

use AspectMock\Test as test;

final class MonitorTest extends TestBase
{
    private $key = 'monitor1234';
    private $apiKey = 'api1234';
    private $apiVersion = '2020-01-01';
    private $env = 'test';

    protected function setUp(): void
    {
        $this->monitor = new \Cronitor\Monitor($this->key, $this->apiKey, $this->apiVersion, $this->env);
    }

    public function testIsInitializable()
    {
        $this->assertEquals($this->key, $this->monitor->key);
        $this->assertEquals($this->apiKey, $this->monitor->apiKey);
        $this->assertEquals($this->apiVersion, $this->monitor->apiVersion);
        $this->assertEquals($this->env, $this->monitor->env);
    }

    public function testPut()
    {
        $params = [
            'monitors' => [],
            'rollback' => true
        ];
        $mockResponse = [
            'code' => 200,
            'content' => "{\"monitors\": [{\"key\": \"$this->key\"}]}"
        ];
        $mockHttpClient = test::double('Cronitor\HttpClient', ['put' => $mockResponse]);

        $result = \Cronitor\Monitor::put($this->apiKey, $this->apiVersion, $params);
        $this->assertInstanceOf(\Cronitor\Monitor::class, $result);
        $this->assertEquals($this->key, $result->key);
    }

    public function testDelete()
    {
        $mockResponse = [
            'code' => 204,
            'content' => ""
        ];
        $mockHttpClient = test::double('Cronitor\HttpClient', ['delete' => $mockResponse]);

        $result = \Cronitor\Monitor::delete($this->apiKey, $this->apiVersion, $this->key);
        $this->assertEquals($mockResponse, $result);
    }

    public function testPing()
    {
        $mockResponse = [
            'code' => 200,
            'content' => ""
        ];
        $mockHttpClient = test::double('Cronitor\HttpClient', ['get' => $mockResponse]);

        $result = $this->monitor->ping([]);
        $this->assertTrue($result);
    }

    public function testPause()
    {
        $mockResponse = [
            'code' => 200,
            'content' => ""
        ];
        $mockHttpClient = test::double('Cronitor\HttpClient', ['get' => $mockResponse]);

        $result = $this->monitor->pause();
        $this->assertTrue($result);
    }

    public function testUnpause()
    {
        $mockResponse = [
            'code' => 200,
            'content' => ""
        ];
        $mockHttpClient = test::double('Cronitor\HttpClient', ['get' => $mockResponse]);

        $result = $this->monitor->unpause();
        $this->assertTrue($result);
    }

    public function testGetData()
    {
        $mockResponse = [
            'code' => 200,
            'content' => ""
        ];
        $mockHttpClient = test::double('Cronitor\HttpClient', ['get' => $mockResponse]);

        $result = $this->monitor->getData();
        $this->assertEquals('', $result);
    }

    public function testSetData()
    {
        $data = ["monitors" => []];

        $result = $this->monitor->setData($data);
        $this->assertTrue($result);
        $this->assertEquals($data, $this->monitor->getData());
    }

    public function testOk()
    {
        $mockResponse = [
            'code' => 200,
            'content' => ""
        ];
        $mockHttpClient = test::double('Cronitor\HttpClient', ['get' => $mockResponse]);

        $result = $this->monitor->ok();
        $this->assertTrue($result);
    }
}

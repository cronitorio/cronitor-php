<?php

namespace Cronitor\Tests;

use AspectMock\Test as test;

final class MonitorsTest extends TestBase
{
    private $monitors;
    private $apiKey = '1234';
    private $apiVersion = '2020-01-01';

    protected function setUp(): void
    {
        $this->monitors = new \Cronitor\Monitors($this->apiKey, $this->apiVersion);
    }

    public function testIsInitializable()
    {
        $this->assertEquals($this->apiKey, $this->monitors->apiKey);
        $this->assertEquals($this->apiVersion, $this->monitors->apiVersion);
    }

    public function testPut()
    {
        $monitor = test::double('\Cronitor\Monitor', ['put' => true]);
        $this->assertTrue($this->monitors->put([]));
        $monitor->verifyInvokedOnce('put');
    }

    public function testDelete()
    {
        $monitor = test::double('\Cronitor\Monitor', ['delete' => true]);
        $this->assertTrue($this->monitors->delete([]));
        $monitor->verifyInvokedOnce('delete');
    }
}

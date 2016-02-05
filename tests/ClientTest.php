<?php

namespace Cronitor\Tests;

use Cronitor\Client;

class ClientTest extends \PHPUnit_Framework_TestCase
{

    public $client;
    protected $okResponse;

    public function setUp()
    {
        $this->okResponse = new \stdClass();
        $this->okResponse->http_code = 200;
    }

    public function test_it_should_run()
    {
        $client = $this->getOkClient();

        $response = $client->run();
        $this->assertEquals($response->http_code, 200);
    }

    public function test_it_should_complete()
    {
        $client = $this->getOkClient();

        $response = $client->complete();
        $this->assertEquals($response->http_code, 200);
    }

    public function test_it_should_fail()
    {
        $client = $this->getOkClient();

        $response = $client->fail('It failed!');
        $this->assertEquals($response->http_code, 200);
    }

    public function test_it_should_pause()
    {
        $client = $this->getOkClient();

        $response = $client->pause(1);
        $this->assertEquals($response->http_code, 200);
    }

    protected function getOkClient()
    {
        $client = $this->getMockBuilder('\Cronitor\Client')
            ->setConstructorArgs(array('faked!'))
            ->setMethods(array('request'))
            ->getMock();

        $client->expects($this->once())
            ->method('request')
            ->will($this->returnValue($this->okResponse));

        return $client;
    }
}

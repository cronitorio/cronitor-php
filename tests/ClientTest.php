<?php

namespace Cronitor\Tests;

use Cronitor\Client;
use anlutro\cURL;

class ClientTest extends \PHPUnit_Framework_TestCase
{

    public $client;
    protected $okResponse;

    public function setUp()
	{

		$okHeaders = "HTTP/1.1 200 OK
Server: nginx/1.4.6 (Ubuntu)
Date: Mon, 08 Feb 2016 22:42:43 GMT
Content-Length: 0
Connection: close";

		$this->okResponse = new cURL\Response('', $okHeaders);
    }

    public function test_it_should_run()
    {
        $client = $this->getOkClient();

        $response = $client->run();
        $this->assertEquals($response->statusCode, 200);
    }

    public function test_it_should_complete()
    {
        $client = $this->getOkClient();

        $response = $client->complete();
        $this->assertEquals($response->statusCode, 200);
    }

    public function test_it_should_fail()
    {
        $client = $this->getOkClient();

        $response = $client->fail('It failed!');
        $this->assertEquals($response->statusCode, 200);
    }

    public function test_it_should_pause()
    {
        $client = $this->getOkClient();

        $response = $client->pause(1);
        $this->assertEquals($response->statusCode, 200);
    }

	public function test_it_should_build_url()
	{
		$client = new Client('boogers');
		$this->assertEquals('https://cronitor.link/boogers/run', $client->buildUrl('run'));
	}

	public function test_it_should_build_url_with_auth_key()
	{
		$client = new Client('boogers', '123abc');
		$this->assertEquals('https://cronitor.link/boogers/run?auth_key=123abc', $client->buildUrl('run'));
	}

    protected function getOkClient()
    {

		$okCurl = $this->getOkCurl();

        $client = $this->getMockBuilder('\Cronitor\Client')
            ->setConstructorArgs(array('boogers'))
            ->setMethods(array('getcUrl'))
            ->getMock();

        $client->expects($this->once())
            ->method('getcUrl')
			->will($this->returnValue($okCurl));

        return $client;
	}

	protected function getOkcUrl()
	{

		$curl = $this->getMockBuilder('\anlutro\cURL\cURL')
            ->setMethods(array('sendRequest'))
            ->getMock();

        $curl->expects($this->once())
            ->method('sendRequest')
			->will($this->returnValue($this->okResponse));

		return $curl;
	}
}

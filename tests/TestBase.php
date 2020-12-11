<?php

namespace Cronitor\Tests;

use anlutro\cURL;
use Cronitor\Client;
use PHPUnit\Framework\TestCase;

class TestBase extends TestCase
{
    public $client;
    protected $okResponse;

    protected function setUp(): void
    {
        $okHeaders = 'HTTP/1.1 200 OK
Server: nginx/1.4.6 (Ubuntu)
Date: Mon, 08 Feb 2016 22:42:43 GMT
Content-Length: 0
Connection: close';

        $this->okResponse = new cURL\Response('', $okHeaders);
    }

    protected function getOkClient()
    {
        $client = $this->getMockBuilder('\Cronitor\Client')
            ->setConstructorArgs(array('boogers'))
            ->setMethods(array('getcUrl'))
            ->getMock();

        $client = $this->setClientOkCurl($client);

        return $client;
    }

    protected function setClientOkCurl($client)
    {
        $client->expects($this->atLeastOnce())
            ->method('getcUrl')
            ->will($this->returnValue($this->getOkCurl()));
        return $client;
    }

    protected function getOkcUrl()
    {
        $curl = $this->getMockBuilder('\anlutro\cURL\cURL')
            ->setMethods(array('sendRequest'))
            ->getMock();

        $curl->expects($this->atLeastOnce())
            ->method('sendRequest')
            ->will($this->returnValue($this->okResponse));

        return $curl;
    }
}

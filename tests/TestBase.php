<?php

namespace Cronitor\Tests;

use Cronitor\Client;
use anlutro\cURL;

class TestBase extends \PHPUnit_Framework_TestCase
{
    public $client;
    protected $okResponse;

    public function setUp()
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

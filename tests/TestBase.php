<?php

namespace Cronitor\Tests;

use Cronitor\Caller;
use anlutro\cURL;

class TestBase extends \PHPUnit_Framework_TestCase
{
    public $caller;
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

    protected function getOkCaller()
    {
        $caller = $this->getMockBuilder('\Cronitor\Caller')
            ->setConstructorArgs(array('boogers'))
            ->setMethods(array('getcUrl'))
            ->getMock();

        $caller = $this->setCallerOkCurl($caller);

        return $caller;
    }

    protected function setCallerOkCurl($caller)
    {
        $caller->expects($this->atLeastOnce())
            ->method('getcUrl')
            ->will($this->returnValue($this->getOkCurl()));
        return $caller;
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

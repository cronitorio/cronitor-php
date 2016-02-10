<?php

namespace Cronitor\Tests;

use Cronitor\Client;
use anlutro\cURL;

class MonitorTaskTest extends TestBase
{

    public function test_it_should_return_value_on_success()
    {
        $client = $this->getOkClient();

        $returns = cronitorMonitorTask(
            $client,
            function () {
                return 'it works!';
            }
        );

        $this->assertEquals($returns, 'it works!');
    }

    public function test_it_should_return_null_value_on_success()
    {
        $client = $this->getOkClient();

        $returns = cronitorMonitorTask(
            $client,
            function () {
            }
        );

        $this->assertEquals($returns, null);
    }

    /**
     * @expectedException     Exception
     */
    public function test_it_should_send_exceptions_message_on_failure()
    {
        $msg = 'This is bogus!';

        $client = $this->getMockBuilder('\Cronitor\Client')
            ->setConstructorArgs(array('boogers'))
            ->setMethods(array('getcUrl', 'fail'))
            ->getMock();

        $client = $this->setClientOkCurl($client);

        $client->expects($this->once())
            ->method('fail')
            ->with($msg);

        cronitorMonitorTask(
            $client,
            function () use ($msg) {
                throw new \Exception($msg);
            }
        );
    }

    /**
     * @expectedException     Exception
     */
    public function test_it_should_send_exception_handlers_message_on_failure()
    {
        $msg = 'This is super bogus!';

        $client = $this->getMockBuilder('\Cronitor\Client')
            ->setConstructorArgs(array('boogers'))
            ->setMethods(array('getcUrl', 'fail'))
            ->getMock();

        $client = $this->setClientOkCurl($client);

        $client->expects($this->once())
            ->method('fail')
            ->with($msg);

        cronitorMonitorTask(
            $client,
            function () use ($msg) {
                throw new \Exception($msg);
            },
            function ($e) {
                return array('msg' => $e->getMessage());
            }
        );
    }

    /**
     * @expectedException     Exception
     */
    public function test_it_should_pause_when_set_on_failure()
    {
        $msg = 'This is mega bogus!';
        $interval = 2;

        $client = $this->getMockBuilder('\Cronitor\Client')
            ->setConstructorArgs(array('boogers'))
            ->setMethods(array('getcUrl', 'pause'))
            ->getMock();

        $client = $this->setClientOkCurl($client);

        $client->expects($this->once())
            ->method('pause')
            ->with($interval);

        cronitorMonitorTask(
            $client,
            function () use ($msg) {
                throw new \Exception($msg);
            },
            function ($e) use ($interval) {
                return array('msg' => $e->getMessage(), 'pause' => $interval);
            }
        );
    }
}

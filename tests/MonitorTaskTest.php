<?php

namespace Cronitor\Tests;

use Cronitor\Caller;
use anlutro\cURL;

class MonitorTaskTest extends TestBase
{

    public function test_it_should_return_value_on_success()
    {
        $caller = $this->getOkCaller();

        $returns = cronitorMonitorTask(
            $caller,
            function () {
                return 'it works!';
            }
        );

        $this->assertEquals($returns, 'it works!');
    }

    public function test_it_should_return_null_value_on_success()
    {
        $caller = $this->getOkCaller();

        $returns = cronitorMonitorTask(
            $caller,
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

        $caller = $this->getMockBuilder('\Cronitor\Caller')
            ->setConstructorArgs(array('boogers'))
            ->setMethods(array('getcUrl', 'fail'))
            ->getMock();

        $caller = $this->setCallerOkCurl($caller);

        $caller->expects($this->once())
            ->method('fail')
            ->with($msg);

        cronitorMonitorTask(
            $caller,
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

        $caller = $this->getMockBuilder('\Cronitor\Caller')
            ->setConstructorArgs(array('boogers'))
            ->setMethods(array('getcUrl', 'fail'))
            ->getMock();

        $caller = $this->setCallerOkCurl($caller);

        $caller->expects($this->once())
            ->method('fail')
            ->with($msg);

        cronitorMonitorTask(
            $caller,
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

        $caller = $this->getMockBuilder('\Cronitor\Caller')
            ->setConstructorArgs(array('boogers'))
            ->setMethods(array('getcUrl', 'pause'))
            ->getMock();

        $caller = $this->setCallerOkCurl($caller);

        $caller->expects($this->once())
            ->method('pause')
            ->with($interval);

        cronitorMonitorTask(
            $caller,
            function () use ($msg) {
                throw new \Exception($msg);
            },
            function ($e) use ($interval) {
                return array('msg' => $e->getMessage(), 'pause' => $interval);
            }
        );
    }
}

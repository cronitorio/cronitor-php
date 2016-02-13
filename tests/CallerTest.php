<?php

namespace Cronitor\Tests;

use Cronitor\Caller;
use anlutro\cURL;

class CallerTest extends TestBase
{

    public function test_it_should_run()
    {
        $caller = $this->getOkCaller();

        $response = $caller->run();
        $this->assertEquals($response->statusCode, 200);
    }

    public function test_it_should_complete()
    {
        $caller = $this->getOkCaller();

        $response = $caller->complete();
        $this->assertEquals($response->statusCode, 200);
    }

    public function test_it_should_fail()
    {
        $caller = $this->getOkCaller();

        $response = $caller->fail('It failed!');
        $this->assertEquals($response->statusCode, 200);
    }

    public function test_it_should_pause()
    {
        $caller = $this->getOkCaller();

        $response = $caller->pause(1);
        $this->assertEquals($response->statusCode, 200);
    }

    public function test_it_should_build_url()
    {
        $caller = new Caller('boogers');
        $this->assertEquals('https://cronitor.link/boogers/run', $caller->buildUrl('run'));
    }

    public function test_it_should_build_url_with_auth_key()
    {
        $caller = new Caller('boogers', '123abc');
        $this->assertEquals('https://cronitor.link/boogers/run?auth_key=123abc', $caller->buildUrl('run'));
    }

    public function test_it_should_get_curl()
    {
        $caller = new Caller('boogers');
        $this->assertEquals(new cUrl\cUrl, $caller->getcUrl());
    }
}

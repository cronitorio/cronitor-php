<?php

namespace Cronitor\Tests;

use Cronitor\Client;
use anlutro\cURL;

class ClientTest extends TestBase
{

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

    public function test_it_should_get_curl()
    {
        $client = new Client('boogers');
        $this->assertEquals(new cUrl\cUrl, $client->getcUrl());
    }
}

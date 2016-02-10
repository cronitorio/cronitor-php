<?php

namespace Cronitor\Tests;

use Cronitor\Client;
use anlutro\cURL;

class MonitorTaskTest extends TestBase
{

    public function test_it_should_run()
    {
        $client = $this->getOkClient();

        $response = $client->run();
        $this->assertEquals($response->statusCode, 200);
    }
}

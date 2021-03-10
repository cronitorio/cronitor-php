<?php

namespace Cronitor\Tests;

use PHPUnit\Framework\TestCase;
use AspectMock\Test as test;

final class ClientTest extends TestBase
{
    private $client;
    private $apiKey = '1234';
    private $apiVersion = '2020-01-01';
    private $environment = 'staging';

    protected function setUp(): void
    {
        $this->client = new \Cronitor\Client($this->apiKey, $this->apiVersion, $this->environment);
    }

    public function testIsInitializable()
    {
        $this->assertEquals($this->apiKey, $this->client->apiKey);
        $this->assertEquals($this->apiVersion, $this->client->apiVersion);
        $this->assertEquals($this->environment, $this->client->environment);
        $this->assertInstanceOf(\Cronitor\Monitors::class, $this->client->monitors);
    }

    public function testMonitor()
    {
        $monitorKey = '1234';
        $monitor = $this->client->monitor($monitorKey);
        $this->assertInstanceOf(\Cronitor\Monitor::class, $monitor);
    }

    public function testReadConfig()
    {
        $dataConfig = [
            "jobs" => [
                "replenishment-report" => [
                    "schedule" => "0 * * * *"
                ],
                'data-warehouse-exports' => [
                    'schedule' => '0 0 * * *'
                ],
                'welcome-email' => [
                    'schedule' => 'every 10 minutes'
                ]
            ],
            'synthetics' => [
                'cronitor-homepage' => [
                    'request' => [
                        'url' => 'https://cronitor.io'
                    ],
                    'assertions' => ['response.time < 2s']
                ]
            ],
            'events' => [
                'production-deploy' => [
                    'notify' => [
                        'alerts' => ['default'],
                        'events' => [
                            'complete' => true
                        ]
                    ]
                ]
            ],
            'monitors' => [
                [
                    'key' => 'replenishment-report',
                    'schedule' => '0 * * * *',
                    'type' => 'job'
                ],
                [
                    'key' => 'data-warehouse-exports',
                    'schedule' => '0 0 * * *',
                    'type' => 'job'
                ],
                [
                    'key' => 'welcome-email',
                    'schedule' => 'every 10 minutes',
                    'type' => 'job'
                ],
                [
                    'key' => 'production-deploy',
                    'type' => 'event',
                    "notify" => [
                        "alerts" => ["default"],
                        "events" => ["complete" => true]
                    ]
                ],
                [
                    'key' => 'cronitor-homepage',
                    'type' => 'synthetic',
                    'request' => ['url' => 'https://cronitor.io'],
                    'assertions' => ['response.time < 2s']
                ]
            ]
        ];
        $returnedConfig = $this->client->readConfig('tests/data/config.yml', true);
        $this->assertEquals($dataConfig, $returnedConfig);
    }

    public function testApplyConfig()
    {
        $this->client->readConfig('tests/data/config.yml');
        test::double('\Cronitor\Monitor', ['put' => []]);
        $this->assertTrue($this->client->applyConfig());
    }

    public function testValidateConfig()
    {
        $this->client->readConfig('tests/data/config.yml');
        test::double('\Cronitor\Monitor', ['put' => []]);
        $this->assertTrue($this->client->validateConfig());
    }

    public function testJob()
    {
        $callback = function () {
            return 'success';
        };
        $jobResult = $this->client->job('1234', $callback);
        $this->assertEquals('success', $jobResult);
    }
}

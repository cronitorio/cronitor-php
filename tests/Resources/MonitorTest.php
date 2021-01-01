<?php declare(strict_types=1);

namespace Cronitor\Tests\Resources;

use Cronitor\Cronitor;
use Nyholm\Psr7\Response;
use Cronitor\Resources\Monitor;
use PHPUnit\Framework\TestCase;
use JustSteveKing\HttpSlim\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

class MonitorTest extends TestCase
{
    protected Cronitor $cronitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cronitor = Cronitor::config(__DIR__ . '/../data/config.yml');

        $this->cronitor->monitor->setHttp(
            HttpClient::build(
                new \Http\Mock\Client(),
                new Psr18Client(),
                new Psr18Client()
            )
        );
    }

    /**
     * @test
     */
    public function it_will_allow_us_to_create_a_monitor_class_manually()
    {
        $monitor = new Monitor(
            '1234-1234',
            '2020-10-01',
            'staging',
            'cronitor-php'
        );

        $this->assertInstanceOf(
            Monitor::class,
            $monitor
        );
    }

    /**
     * @test
     */
    public function it_can_send_a_put_request()
    {
        $response = $this->cronitor->monitor->put([
            'monitors' => [
                [
                    'type' => \Cronitor\Resources\Monitor::JOB,
                    'key' => '123456',
                    'schedule' => 'every 5 days'
                ]
            ],
            'rollback' => true
        ]);

        $this->assertInstanceOf(
            Response::class,
            $response
        );

        $this->assertEquals(
            200,
            $response->getStatusCode()
        );
    }

    /**
     * @test
     */
    public function it_has_the_correct_default_headers()
    {
        $this->assertEquals(
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => $this->cronitor->getUserAgent(),
                'Cronitor-Version' => $this->cronitor->getApiVersion(),
            ],
            $this->cronitor->monitor->defaultHeaders()
        );
    }
}

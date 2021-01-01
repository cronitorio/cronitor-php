<?php declare(strict_types=1);

namespace Cronitor\Tests;

use Cronitor\Cronitor;
use Cronitor\Resources\Monitor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Cronitor\Exceptions\ResourceException;
use Cronitor\Exceptions\ConfigurationException;

class CronitorTest extends TestCase
{
    protected function createCronitor(string $file): Cronitor
    {
        return Cronitor::config($file);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_if_config_file_doese_not_exist()
    {
        $this->expectException(ConfigurationException::class);

        $cronitor = $this->createCronitor(__DIR__ . '/data/fake-config.yml');
    }

    /**
     * @test
     */
    public function it_allows_you_to_create_a_cronitor_class_using_yaml_config()
    {
        $file = __DIR__ . '/data/config.yml';

        $contents = Yaml::parseFile($file);
        $cronitor = $this->createCronitor($file);

        $this->assertInstanceOf(
            Cronitor::class,
            $cronitor
        );

        $this->assertEquals(
            $cronitor->getApiKey(),
            $contents['api_key']
        );

        $this->assertEquals(
            $cronitor->getEnvironment(),
            $contents['environment']
        );

        $this->assertEquals(
            $cronitor->getApiVersion(),
            $contents['api_version']
        );

        $this->assertEquals(
            $cronitor->getConfigFile(),
            $file
        );
    }

    /**
     * @test
     */
    public function it_allows_us_to_build_up_our_cronitor_object_through_the_constructor()
    {
        $cronitor = new Cronitor(
            '1234-1234',
            'staging',
            '2020-10-01'
        );

        $this->assertInstanceOf(
            Cronitor::class,
            $cronitor
        );

        $this->assertEquals(
            '1234-1234',
            $cronitor->getApiKey()
        );

        $this->assertEquals(
            'staging',
            $cronitor->getEnvironment()
        );

        $this->assertEquals(
            '2020-10-01',
            $cronitor->getApiVersion()
        );
    }

    /**
     * @test
     */
    public function it_throws_an_exception_if_a_resource_does_not_exist()
    {
        $this->expectException(ResourceException::class);
        $cronitor = $this->createCronitor(__DIR__ . '/data/config.yml');

        $cronitor->fakeResource->get();
    }

    /**
     * @test
     */
    public function it_will_return_the_resource_through_the_magic_method()
    {
        $cronitor = $this->createCronitor(__DIR__ . '/data/config.yml');

        $this->assertInstanceOf(
            Monitor::class,
            $cronitor->monitor
        );
    }
}

<?php declare(strict_types=1);

namespace Cronitor;

use Exception;
use DI\Container;
use Cronitor\Resources\Monitor;
use Symfony\Component\Yaml\Yaml;
use Psr\Container\ContainerInterface;
use Cronitor\Exceptions\ResourceException;
use Cronitor\Exceptions\ConfigurationException;

class Cronitor
{
    /**
     * @var string
     */
    protected string $apiKey;

    /**
     * @var string
     */
    protected string $apiVersion;

    /**
     * @var string
     */
    protected string $environment;

    /**
     * @var string
     */
    protected string $userAgent = 'cronitor-php';

    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Cronitor constructor.
     * @param string $apiKey
     * @param string $environment
     * @param string $version
     */
    public function __construct(
        string $apiKey,
        string $environment,
        string $version = '2020-10-01'
    ) {
        $this->setApiKey($apiKey)
            ->setApiVersion($version)
            ->setEnvironment($environment)
            ->setContainer();
    }

    /**
     * @param string $file
     * @return self
     * @throws ConfigurationException
     */
    public static function config(string $file): self
    {
        try {
            $contents = Yaml::parseFile($file);
        } catch (Exception $e) {
            throw new ConfigurationException($e->getMessage());
        }

        if (! array_key_exists('api_key', $contents) || ! array_key_exists('environment', $contents)) {
            throw new ConfigurationException(
                "Cannot create a Cronitor object without an API Key and Environment"
            );
        }

        return new self(
            $contents['api_key'],
            $contents['environment'],
            $contents['api_version'] ?? null
        );
    }

    /**
     * @param string $apiKey
     * @return self
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * @param string $environment
     * @return self
     */
    public function setEnvironment(string $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * @param string $version
     * @return self
     */
    public function setApiVersion(string $version): self
    {
        $this->apiVersion = $version;

        return $this;
    }

    /**
     * @param string $userAgent
     * @return $this
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    /**
     * @return $this
     */
    public function setContainer(): self
    {
        $this->container = new Container();

        $this->container->set('monitor', new Monitor(
            $this->apiKey,
            $this->apiVersion,
            $this->environment,
            $this->userAgent,
        ));

        return $this;
    }

    public function __get(string $name)
    {
        if (! $this->container->has($name)) {
            throw new ResourceException(
                "Resource {$name} has not been registered on Cronitor PHP SDK."
            );
        }

        $resource = $this->container->get($name);

        return $resource;
    }
}

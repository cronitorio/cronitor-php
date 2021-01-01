<?php declare(strict_types=1);

namespace Cronitor\Resources;

use Exception;
use Nyholm\Psr7\Response;
use JustSteveKing\HttpSlim\HttpClient;
use Cronitor\Exceptions\ResourceException;
use Symfony\Component\HttpClient\Psr18Client;
use JustSteveKing\HttpAuth\Strategies\BasicStrategy;

class Monitor
{
    /**
     * @var string
     */
    public const JOB = 'job';

    /**
     * @var string
     */
    public const EVENT = 'event';

    /**
     * @var string
     */
    public const SYNTHETIC = 'synthetic';

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
    protected string $userAgent;

    /**
     * @var HttpClient
     */
    protected HttpClient $http;

    public function __construct(
        string $apiKey,
        string $apiVersion,
        string $environment,
        string $userAgent
    ) {
        $this->apiKey = $apiKey;
        $this->apiVersion = $apiVersion;
        $this->environment = $environment;
        $this->userAgent = $userAgent;

        $this->http = HttpClient::build(
            new Psr18Client(),
            new Psr18Client(),
            new Psr18Client()
        );
    }


    public function put($payload): Response
    {
        try {
            $response = $this->http->put(
                'https://cronitor.io/api/monitors',
                $payload,
                array_merge(
                    $this->defaultHeaders(),
                    (new BasicStrategy(
                        base64_encode("{$this->apiKey}:")
                    ))->getHeader('Basic')
                )
            );
        } catch (Exception $e) {
            throw new ResourceException($e->getMessage());
        }

        return $response;
    }

    /**
     * @param HttpClient $http
     *
     * @return $this
     */
    public function setHttp(HttpClient $http): self
    {
        $this->http = $http;

        return $this;
    }

    /**
     * @return HttpClient
     */
    public function getHttp(): HttpClient
    {
        return $this->http;
    }

    /**
     * @return array
     */
    public function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
            'Cronitor-Version' => $this->apiVersion,
        ];
    }

    public function delete()
    {
        //
    }

    public function ping()
    {
        //
    }

    /**
     * @param string $key The key of the monitor to get data on.
     */
    public function data(string $key): Response
    {
        try {
            $response = $this->http->get(
                'https://cronitor.io/api/monitors/' . $key,
                array_merge(
                    $this->defaultHeaders(),
                    (new BasicStrategy(
                        base64_encode("{$this->apiKey}:")
                    ))->getHeader('Basic')
                )
            );
        } catch (Exception $e) {
            throw new ResourceException($e->getMessage());
        }

        return $response;
    }

    public function pause()
    {
        //
    }

    public function unpause()
    {
        //
    }

    public function ok()
    {
        //
    }
}

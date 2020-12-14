<?php declare(strict_types=1);

namespace Cronitor\Resources;

use Exception;
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


    public function put($payload)
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
            dump($response->getStatusCode());
        } catch (Exception $e) {
            throw new ResourceException($e->getMessage());
        }

        dd($response->getBody()->getContents());
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

    public function data()
    {
        //
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

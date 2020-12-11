<?php declare(strict_types=1);

namespace Cronitor\Resources;

use Cronitor\Exceptions\ResourceException;
use Exception;
use JustSteveKing\HttpAuth\Strategies\BasicStrategy;
use JustSteveKing\HttpSlim\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

class Monitor
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

    /**
     * @param mixed ...$monitors
     */
    public function put(...$monitors)
    {
        foreach ($monitors as $monitor) {
            try {
                $response = $this->http->post(
                    'https://cronitor.io/api/monitors',
                    (array) $monitor,
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

<?php

namespace Cronitor;

class Monitor
{
    private const BASE_MONITOR_API_URL = 'https://cronitor.io/api/monitors';
    private const BASE_PING_API_URL = "https://cronitor.link/p";
    private const BASE_FALLBACK_PING_API_URL = "https://cronitor.io/p";
    private const PING_RETRY_THRESHOLD = 5;

    public $apiKey;
    public $apiVersion;
    public $key;
    public $env;

    private $monitorClient;

    public function __construct($key, $apiKey = null, $apiVersion = null, $env = null)
    {
        $this->apiKey = $apiKey;
        $this->apiVersion = $apiVersion;
        $this->key = $key;
        $this->env = $env;

        $monitorApiUrl = self::BASE_MONITOR_API_URL . "/$key";
        $this->monitorClient = new HttpClient($monitorApiUrl, $this->apiKey, $this->apiVersion);
    }

    public static function put($apiKey, $apiVersion, $params = [])
    {
        $rollback = isset($params['rollback']) ? $params['rollback'] : false;
        unset($params['rollback']);
        $monitors = isset($params['monitors']) ? $params['monitors'] : [$params];

        $client = self::getMonitorHttpClient($apiKey, $apiVersion);
        $response = $client->put('', [
            'monitors' => $monitors,
            'rollback' => $rollback,
        ], ['timeout' => 10]);

        $code = $response['code'];
        switch ($code) {
            case 200:
                $out = [];
                $data = json_decode($response['content'], true);

                $dataMonitors = isset($data['monitors']) ? $data['monitors'] : [];
                foreach ($dataMonitors as &$md) {
                    $m = new Monitor($md['key']);
                    $m->data = $md;
                    array_push($out, $m);
                }
                return count($out) == 1 ? $out[0] : $out;
                break;
            case 400:
                throw new ValidationException($response['content']);
            default:
                throw new \Exception("Error connecting to Cronitor: $code");
        }
    }

    public static function getYaml($apiKey, $apiVersion)
    {
        $client = self::getMonitorHttpClient($apiKey, $apiVersion);
        $response = $client->get('.yaml', ['timeout' => 25]);
        $content = $response['content'];
        if ($response['code'] == 200) {
            return $content;
        }

        throw new \Exception("Unexpected error: $content");
    }

    public static function delete($apiKey, $apiVersion, $key)
    {
        $client = self::getMonitorHttpClient($apiKey, $apiVersion);
        $response = $client->delete("/$key", ['timeout' => 10]);

        if ($response['code'] != 204) {
            \error_log("Error deleting monitor: $key", 0);
            return false;
        }
        return $response;
    }

    public function ping($params = array())
    {
        $retryCount = isset($params['retryCount']) ? $params['retryCount'] : 0;

        if (!$this->apiKey) {
            \error_log('No API key detected. Set Cronitor.api_key or initialize Monitor with an api_key:', 0);
            return false;
        }

        try {
            $queryString = $this->buildPingQuery($params);
            $client = $this->getPingClient($retryCount);
            $response = $client->get("?$queryString");
            $responseCode = $response['code'];

            if ($responseCode !== 200) {
                \error_log("Cronitor Telemetry Error: $responseCode", 0);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            // rescue instances of StandardError i.e. Timeout::Error, SocketError, etc
            \error_log("Cronitor Telemetry Error: $e", 0);
            if ($retryCount >= self::PING_RETRY_THRESHOLD) {
                return false;
            }

            // apply a backoff before sending the next ping
            sleep($this->calculateSleep($retryCount));
            $this->ping(array_merge($params, ['retryCount' => $retryCount + 1]));
        }
    }

    public function pause($hours = null)
    {
        $path = '/pause';
        if ($hours) {
            $path .= "/$hours";
        }

        $response = $this->monitorClient->get($path, ['timeout' => 5]);
        return $response['code'] >= 200 && $response['code'] <= 299;
    }

    public function unpause()
    {
        return $this->pause(0);
    }

    public function getData()
    {
        if (isset($this->data)) {
            return $this->data;
        }

        if (!$this->apiKey) {
            \error_log('No API key detected. Initialize CronitorClient with a valid API key.', 0);
            return null;
        }

        $response = $this->monitorClient->get('', ['timeout' => 10]);
        $this->data = json_decode($response['content']);
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
        return true;
    }

    public function ok()
    {
        return $this->ping(['state' => 'ok']);
    }

    private static function getMonitorHttpClient($apiKey, $apiVersion)
    {
        return new HttpClient(self::BASE_MONITOR_API_URL, $apiKey, $apiVersion);
    }

    private function cleanParams($params)
    {
        $cleanedParams = [
            'state' => isset($params['state']) ? $params['state'] : null,
            'message' => isset($params['message']) ? $params['message'] : null,
            'series' => isset($params['series']) ? $params['series'] : null,
            'host' => isset($params['host']) ? $params['host'] : gethostname(),
            'metric' => isset($params['metrics']) ? $this->cleanMetrics($params['metrics']) : null,
            'stamp' => microtime(true),
            'env' => isset($params['env']) ? $params['env'] : null,
        ];

        $filteredParams = array_filter($cleanedParams, function ($v) {
            return !is_null($v);
        });

        return $filteredParams;
    }

    private function cleanMetrics($metrics)
    {
        return array_map(function ($key) {
            $value = $metrics[$key];
            return "$key:$value";
        }, array_keys($metrics));
    }

    private function getPingClient($retryCount)
    {
        $url = $retryCount > (self::PING_RETRY_THRESHOLD / 2) ? $this->getFallbackPingApiUrl() : $this->getPingApiUrl();
        return new HttpClient($url, $this->apiKey, $this->apiVersion);
    }
    private function getPingApiUrl()
    {
        return self::BASE_PING_API_URL . "/$this->apiKey/$this->key";
    }

    private function getFallbackPingApiUrl()
    {
        return self::BASE_FALLBACK_PING_API_URL . "/$this->apiKey/$this->key";
    }

    private function buildPingQuery($params)
    {
        $cleanParams = $this->cleanParams($params);
        $metrics = isset($cleanParams['metric']) ? $cleanParams['metric'] : null;
        unset($cleanParams['metric']);

        $queryParams = array_map(function ($key) use ($cleanParams) {
            $value = $cleanParams[$key];
            return "$key=$value";
        }, array_keys($cleanParams));

        // format query string array params to non-array format, e.g. metric=foo:1
        if ($metrics) {
            $metricParams = array_map(function ($v) {
                return "metric=$v";
            }, $metrics);

            array_push($queryParams, ...$metricParams);
        }

        $query = join('&', $queryParams);

        return $query;
    }

    private function calculateSleep($retryCount)
    {
        $randomFactor = mt_rand(0, 10) / 10;
        return $retryCount * 1.5 * $randomFactor;
    }
}

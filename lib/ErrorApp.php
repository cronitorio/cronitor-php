<?php

namespace Cronitor;

class ErrorApp
{
    const BASE_ERROR_APPS_API_URL = 'https://cronitor.io/api/error-apps';

    public $apiKey;
    public $apiVersion;
    public $key;
    public $data;
    public $dsn;

    private $httpClient;

    public function __construct($key, $apiKey = null, $apiVersion = null)
    {
        $this->key = $key;
        $this->apiKey = $apiKey;
        $this->apiVersion = $apiVersion;
        $this->httpClient = new HttpClient(
            self::BASE_ERROR_APPS_API_URL . "/$key",
            $this->apiKey,
            $this->apiVersion
        );
    }

    public static function create($apiKey, $apiVersion, $params = [])
    {
        $payload = is_string($params) ? ['name' => $params] : (is_array($params) ? $params : []);
        $client = self::getHttpClient($apiKey, $apiVersion);
        $response = $client->post('', $payload, ['timeout' => 10]);
        self::checkResponse($response);

        $data = json_decode($response['content'], true);
        $app = new self($data['key'], $apiKey, $apiVersion);
        $app->setData($data);
        return $app;
    }

    public static function list($apiKey, $apiVersion, $params = [])
    {
        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $pageSize = isset($params['pageSize']) && (int) $params['pageSize'] > 0 ? (int) $params['pageSize'] : 50;
        $autoPaginate = !empty($params['autoPaginate']);

        $client = self::getHttpClient($apiKey, $apiVersion);
        $apps = [];
        $currentPage = $page;

        while (true) {
            $query = http_build_query([
                'page' => $currentPage,
                'pageSize' => $pageSize,
            ]);
            $response = $client->get("?$query", ['timeout' => 10]);
            self::checkResponse($response);

            $body = json_decode($response['content'], true);
            $items = self::extractList($body, ['error_apps', 'apps', 'data']);
            foreach ($items as $item) {
                $app = new self($item['key'], $apiKey, $apiVersion);
                $app->setData($item);
                $apps[] = $app;
            }

            if (!$autoPaginate || count($items) < $pageSize) {
                break;
            }
            $currentPage += 1;
        }

        return $apps;
    }

    public function getData()
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $response = $this->httpClient->get('', ['timeout' => 10]);
        self::checkResponse($response, $this->key);
        $this->setData(json_decode($response['content'], true));
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
        if (is_array($data) && array_key_exists('dsn', $data)) {
            $this->dsn = $data['dsn'];
        } elseif (is_object($data) && isset($data->dsn)) {
            $this->dsn = $data->dsn;
        }
        return true;
    }

    public function update($params = [])
    {
        $payload = is_string($params) ? ['name' => $params] : (is_array($params) ? $params : []);
        $response = $this->httpClient->patch('', $payload, ['timeout' => 10]);
        self::checkResponse($response, $this->key);
        $this->setData(json_decode($response['content'], true));
        return $this;
    }

    public function delete()
    {
        $response = $this->httpClient->delete('', ['timeout' => 10]);
        self::checkResponse($response, $this->key);
        return true;
    }

    public function rotateKey()
    {
        $response = $this->httpClient->post('/rotate-key', null, ['timeout' => 10]);
        self::checkResponse($response, $this->key);
        $this->setData(json_decode($response['content'], true));
        return $this;
    }

    private static function getHttpClient($apiKey, $apiVersion)
    {
        return new HttpClient(self::BASE_ERROR_APPS_API_URL, $apiKey, $apiVersion);
    }

    private static function checkResponse(array $response, $key = null)
    {
        $code = isset($response['code']) ? (int) $response['code'] : 0;
        if ($code >= 200 && $code < 300) {
            return;
        }
        if ($code === 404 && $key !== null) {
            throw new ErrorAppNotFound("ErrorApp '$key' not found");
        }
        if ($code === 400 || $code === 422) {
            throw new ValidationException(isset($response['content']) ? $response['content'] : '');
        }
        throw new \Exception("Error connecting to Cronitor: $code");
    }

    private static function extractList($body, array $keys)
    {
        if (!is_array($body)) {
            return [];
        }
        if (array_keys($body) === range(0, count($body) - 1)) {
            return $body;
        }
        foreach ($keys as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                return $body[$key];
            }
        }
        return [];
    }
}

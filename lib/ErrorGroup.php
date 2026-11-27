<?php

namespace Cronitor;

class ErrorGroup
{
    const BASE_ERRORS_API_URL = 'https://cronitor.io/api/errors';

    public $apiKey;
    public $apiVersion;
    public $key;
    public $data;

    private $httpClient;

    public function __construct($key, $apiKey = null, $apiVersion = null)
    {
        $this->key = $key;
        $this->apiKey = $apiKey;
        $this->apiVersion = $apiVersion;
        $this->httpClient = new HttpClient(
            self::BASE_ERRORS_API_URL . "/$key",
            $this->apiKey,
            $this->apiVersion
        );
    }

    public static function list($apiKey, $apiVersion, $params = [])
    {
        $filterKeys = ['app', 'env', 'status', 'level', 'start', 'end', 'q', 'orderBy'];
        $filters = [];
        foreach ($filterKeys as $name) {
            if (isset($params[$name]) && $params[$name] !== null) {
                $filters[$name] = $params[$name];
            }
        }

        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $pageSize = isset($params['pageSize']) && (int) $params['pageSize'] > 0 ? (int) $params['pageSize'] : 50;
        $autoPaginate = !empty($params['autoPaginate']);

        $client = self::getHttpClient($apiKey, $apiVersion);
        $groups = [];
        $currentPage = $page;

        while (true) {
            $query = http_build_query(array_merge($filters, [
                'page' => $currentPage,
                'pageSize' => $pageSize,
            ]));
            $response = $client->get("?$query", ['timeout' => 10]);
            self::checkResponse($response);

            $body = json_decode($response['content'], true);
            $items = self::extractList($body, ['errors', 'data', 'groups']);
            foreach ($items as $item) {
                $group = new self($item['key'], $apiKey, $apiVersion);
                $group->setData($item);
                $groups[] = $group;
            }

            if (!$autoPaginate || count($items) < $pageSize) {
                break;
            }
            $currentPage += 1;
        }

        return $groups;
    }

    public function getData()
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $response = $this->httpClient->get('', ['timeout' => 10]);
        self::checkResponse($response, "ErrorGroup '{$this->key}' not found");
        $this->setData(json_decode($response['content'], true));
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
        return true;
    }

    public function update($params = [])
    {
        $payload = is_string($params) ? ['status' => $params] : (is_array($params) ? $params : []);
        $response = $this->httpClient->patch('', $payload, ['timeout' => 10]);
        self::checkResponse($response, "ErrorGroup '{$this->key}' not found");
        $this->setData(json_decode($response['content'], true));
        return $this;
    }

    public function events($params = [])
    {
        $queryParams = [
            'limit' => isset($params['limit']) ? (int) $params['limit'] : 50,
        ];
        foreach (['env', 'app', 'cursor'] as $name) {
            if (isset($params[$name]) && $params[$name] !== null) {
                $queryParams[$name] = $params[$name];
            }
        }
        $query = http_build_query($queryParams);
        $response = $this->httpClient->get("/events?$query", ['timeout' => 10]);
        self::checkResponse($response, "ErrorGroup '{$this->key}' not found");
        return json_decode($response['content'], true);
    }

    public function event($eventId)
    {
        $response = $this->httpClient->get("/events/$eventId", ['timeout' => 10]);
        self::checkResponse($response, "Error event '$eventId' not found");
        return json_decode($response['content'], true);
    }

    private static function getHttpClient($apiKey, $apiVersion)
    {
        return new HttpClient(self::BASE_ERRORS_API_URL, $apiKey, $apiVersion);
    }

    private static function checkResponse(array $response, $notFoundMessage = null)
    {
        $code = isset($response['code']) ? (int) $response['code'] : 0;
        if ($code >= 200 && $code < 300) {
            return;
        }
        if ($code === 404 && $notFoundMessage !== null) {
            throw new ErrorGroupNotFound($notFoundMessage);
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

<?php

namespace Cronitor;

class HttpClient
{
    public $baseUrl;
    public $apiVersion;
    public $apiKey;

    public function __construct($baseUrl, $apiKey, $apiVersion)
    {
        $this->baseUrl = $baseUrl;
        $this->apiKey = $apiKey;
        $this->apiVersion = $apiVersion;
    }

    public function get($path, $params = [])
    {
        return $this->request($path, 'GET', $params);
    }

    public function delete($path, $params = [])
    {
        return $this->request($path, 'DELETE', $params);
    }

    public function put($path, $body = [], $params = [])
    {
        return $this->request($path, 'PUT', $params, $body);
    }

    private function request($path, $httpMethod, $params = [], $body = null)
    {
        $options = array(
            CURLOPT_HTTPHEADER => $this->headers(),
            CURLOPT_CUSTOMREQUEST => $httpMethod,
            CURLOPT_USERPWD => $this->apiKey . ":",
            CURLOPT_TIMEOUT => isset($params['timeout']) ? $params['timeout'] : 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => 0,
        );

        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, $options);

        if (!is_null($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
        }

        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [
            'code' => $code,
            'content' => $content,
        ];
    }

    private function headers()
    {
        return [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: cronitor-php',
            "Cronitor-Version: $this->apiVersion",
        ];
    }
}

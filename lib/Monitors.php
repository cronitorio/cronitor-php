<?php

namespace Cronitor;

class Monitors
{
    public $apiKey;
    public $apiVersion;

    public function __construct($apiKey, $apiVersion)
    {
        $this->apiKey = $apiKey;
        $this->apiVersion = $apiVersion;
    }

    public function put($params)
    {
        return Monitor::put($this->apiKey, $this->apiVersion, $params);
    }

    public function delete($key)
    {
        return Monitor::delete($this->apiKey, $this->apiVersion, $key);
    }
}

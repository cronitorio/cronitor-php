<?php

namespace Cronitor;

class ErrorApps
{
    public $apiKey;
    public $apiVersion;

    public function __construct($apiKey, $apiVersion)
    {
        $this->apiKey = $apiKey;
        $this->apiVersion = $apiVersion;
    }

    public function create($params = [])
    {
        return ErrorApp::create($this->apiKey, $this->apiVersion, $params);
    }

    public function list($params = [])
    {
        return ErrorApp::list($this->apiKey, $this->apiVersion, $params);
    }
}

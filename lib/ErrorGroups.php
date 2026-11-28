<?php

namespace Cronitor;

class ErrorGroups
{
    public $apiKey;
    public $apiVersion;

    public function __construct($apiKey, $apiVersion)
    {
        $this->apiKey = $apiKey;
        $this->apiVersion = $apiVersion;
    }

    public function list($params = [])
    {
        return ErrorGroup::list($this->apiKey, $this->apiVersion, $params);
    }
}

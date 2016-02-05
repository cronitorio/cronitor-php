<?php

namespace Cronitor;

class Response
{

    protected $curl;
    protected $response;

    public $headers;
    public $body;
    public $http_code;
    public $last_url;
    public $error;

    public function __construct($curl, $response)
    {
        $this->curl = $curl;
        $this->response = $response;
        $this->parseResponse();
    }

    protected function parseResponse()
    {
        $this->http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        $this->last_url = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
        $this->error = curl_error($this->curl);

        $header_size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $headers = substr($this->response, 0, $header_size);
        $body = substr($this->response, $header_size);

        $this->headers = $this->parseHeaders($headers);
        $this->body = $body;

        return $this;
    }

    protected function parseHeaders($headersString)
    {
        $headers = array();
        $headersArray = explode("\n", $headersString);
        array_shift($headersArray);
        foreach ($headersArray as $header) {
            $parts = explode(':', $header);
            if (isset($parts[1])) {
                $headers[$parts[0]] = $parts[1];
            }
        }
        return $headers;
    }
}

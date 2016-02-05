<?php

namespace Cronitor;

class Client
{

    protected $monitorId;
    protected $authKey;
    protected $baseURI = 'https://cronitor.link';

    public function __construct($monitorId, $authKey = false)
    {
        $this->monitorId = $monitorId;
        $this->authKey = $authKey;
    }

    public function setMonitorId($monitorId)
    {
        $this->monitorId = $monitorId;
    }

    public function setAuthKey($authKey)
    {
        $this->authKey = $authKey;
    }

    public function setBaseURI($baseURI)
    {
        $this->baseURI = $baseURI;
    }

    public function run()
    {
        return $this->request('run');
    }

    public function fail($msg)
    {
        return $this->request('fail', ['msg' => $msg]);
    }

    public function pause($duration)
    {
        return $this->request('pause/' . (int) $duration);
    }

    public function complete()
    {
        return $this->request('complete');
    }

    public function request($endpoint, $parameters = [])
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->buildUrl($endpoint, $parameters),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
        ));
        $response = curl_exec($curl);
        $response = new Response($curl, $response);
        curl_close($curl);
        return $response;
    }

    protected function buildUrl($endpoint, $parameters)
    {
        $url = sprintf(
            '%s/%s/%s',
            $this->baseURI,
            $this->monitorId,
            $endpoint
        );
        $queryString = http_build_query($parameters);
        $url .= (empty($queryString)) ? '' : '?' . $queryString;
        return $url;
    }
}

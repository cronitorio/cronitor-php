<?php

namespace Cronitor;

class Client {

	protected $monitorId;
	protected $authKey;
	protected $baseURI = 'https://cronitor.link';

	public function __construct($monitorId, $authKey = false){
		$this->monitorId = $monitorId;
		$this->authKey = $authKey;
	}

	public function setMonitorId($monitorId){
		$this->monitorId = $monitorId;
	}

	public function setAuthKey($authKey){
		$this->authKey = $authKey;
	}

	public function setBaseURI($baseURI){
		$this->baseURI = $baseURI;
	}

	public function run(){
		return $this->ping('run');
	}

	public function fail($msg){
		return $this->ping('fail', ['msg' => $msg]);
	}

	public function pause($duration){
		return $this->ping('pause/' . (int) $duration);
	}

	public function complete(){
		return $this->ping('complete');
	}

	protected function ping($endpoint, $parameters = []){
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $this->buildUrl($endpoint, $parameters),
			CURLOPT_TIMEOUT => 10,
		));
		return curl_exec($curl);
	}

	protected function buildUrl($endpoint, $parameters){
		$url = "{$this->baseURI}/{$this->monitorId}/{$endpoint}";
		$queryString = http_build_query($parameters);
		$url .= (empty($queryString)) ? '' : "?{$queryString}";
		return $url;
	}
}

<?php

namespace Cronitor;

class Client {

	protected $monitorId;
	protected $authKey;
	protected $baseURI = 'https://cronitor.link';
	protected $endpoints = ['run', 'fail', 'pause', 'complete'];

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

	public function fail(){
		return $this->ping('fail');
	}

	public function pause(){
		return $this->ping('pause');
	}

	public function complete(){
		return $this->ping('complete');
	}

	protected function ping($endpoint){
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => "{$this->baseURI}/{$this->monitorId}/{$endpoint}",
			CURLOPT_TIMEOUT => 10,
		));
		return curl_exec($curl);
	}

}

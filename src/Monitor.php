<?php

namespace Cronitor;

class Task {

	protected $client;

	public function __construct($monitorId, $authKey = false){
		$this->client = new Client($monitorId, $authKey);
	}

	public function monitor($task, $pause = false){
		try{
			$this->client->run();
			$task();
			$this->client->complete();
		} catch (Exception $e){
			$this->client->fail($e->getMessage());
			if($pause){
				$this->client->pause();
			}
		}
	}
}

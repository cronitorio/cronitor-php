<?php

namespace Cronitor;

class Task {

	protected $client;

	public function __construct($monitorId, $authKey = false){
		$this->client = new Client($monitorId, $authKey);
	}

	public function monitor($task, $exceptionHandler = false){
		try{

			$this->client->run();
			$task($this->client);
			$this->client->complete();

		} catch (Exception $exception){

			$pause = false;

			if(!$exceptionHandler){
				$msg = $exception->getMessage();
			} else {
				extract( call_user_func($exceptionHandler, $exception, $this->client) );
			}

			$this->client->fail($msg);
			if($pause){
				$this->client->pause();
			}
		}
	}
}

<?php

function cronitorMonitorTask($client, $closure, $exceptionHandler = false) {
	try {

        $client->run();
        $closure();
		$client->complete();

	} catch (Exception $e) {

		$pause = false;

        if(!$exceptionHandler){
            $msg = $e->getMessage();
		} else {
			// $exceptionHandler should return an array like the following:
			// array(
			//     'msg'   => (string) 'Some string that will act as an error message',
			//     'pause' => (bool) This determines if this particular monitor
			//                is paused in Cronitor. This is an optional value.
			// )
            extract( $exceptionHandler($e, $client) );
        }

        $client->fail($msg);
        if($pause){
            $client->pause();
        }
    }
}

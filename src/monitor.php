<?php

function cronitorMonitorTask($client, $closure, $exceptionHandler = false)
{
    try {
        $client->run();
        $returns = $closure();
        $client->complete();
    } catch (Exception $e) {
        $pause = false;

        if (!$exceptionHandler) {
            $msg = $e->getMessage();
        } else {
            // $exceptionHandler should return an array like the following:
            // array(
            //     'msg'   => (string) 'Some string that will act as an error message',
            //     'pause' => (int) The number of hours to pause this monitor for
            // )
            $handled = $exceptionHandler($e, $client);
            extract($handled);
        }

        $client->fail($msg);

        if ($pause) {
            $client->pause((int) $pause);
        }

        // Let's bubble that exception back up
        throw $e;
    }

    return $returns;
}

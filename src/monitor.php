<?php

function cronitorMonitorTask($caller, $closure, $exceptionHandler = false)
{
    try {
        $caller->run();
        $returns = $closure();
        $caller->complete();
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
            $handled = $exceptionHandler($e, $caller);
            extract($handled);
        }

        $caller->fail($msg);

        if ($pause) {
            $caller->pause((int) $pause);
        }

        // Let's bubble that exception back up
        throw $e;
    }

    return $returns;
}

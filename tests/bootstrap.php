<?php

namespace Cronitor\Tests;

use AspectMock\Test as test;

include __DIR__ . '/../vendor/autoload.php';

$kernel = \AspectMock\Kernel::getInstance();
$kernel->init([
    'debug' => true,
    'includePaths' => [__DIR__ . '/../lib'],
    'cacheDir' => __DIR__ . '/../cache'
]);

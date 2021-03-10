<?php

namespace Cronitor\Tests;

use AspectMock\Test as test;
use PHPUnit\Framework\TestCase;

class TestBase extends TestCase
{
    protected function tearDown(): void
    {
        test::clean();
    }
}

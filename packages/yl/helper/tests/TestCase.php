<?php

namespace Yl\Helper\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Yl\Helper\HelperServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            HelperServiceProvider::class,
        ];
    }
}

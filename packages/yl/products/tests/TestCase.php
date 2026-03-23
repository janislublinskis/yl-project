<?php

namespace Yl\Products\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Yl\Helper\HelperServiceProvider;
use Yl\Products\ProductsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            HelperServiceProvider::class,
            ProductsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
}

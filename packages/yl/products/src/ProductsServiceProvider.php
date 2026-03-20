<?php

namespace Yl\Products;

use Illuminate\Support\ServiceProvider;

/**
 * ProductsServiceProvider
 *
 * Auto-discovered by Laravel via composer.json extra.laravel.providers.
 * Registers routes and migrations for the Products module.
 */
class ProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register migrations — Laravel will run these automatically
        // when `php artisan migrate` is called on the host application.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register API routes under the /api prefix.
        // Using a closure keeps the routes isolated within the module
        // without polluting the host app's routes/api.php file.
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}

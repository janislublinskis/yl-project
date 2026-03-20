<?php

namespace Yl\Posts;

use Illuminate\Support\ServiceProvider;

/**
 * PostsServiceProvider
 *
 * Auto-discovered by Laravel via composer.json extra.laravel.providers.
 * Registers routes and migrations for the Posts module.
 */
class PostsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}

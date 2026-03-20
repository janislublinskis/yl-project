<?php

namespace Yl\Helper;

use Illuminate\Support\ServiceProvider;
use Yl\Helper\Http\Response\ApiResponse;

/**
 * HelperServiceProvider
 *
 * Auto-discovered by Laravel via composer.json extra.laravel.providers.
 * Registers the shared ApiResponse singleton so all modules receive
 * the same instance when resolved from the container.
 */
class HelperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register ApiResponse as a singleton — it is stateless so sharing
        // a single instance across the request lifecycle is safe.
        $this->app->singleton(ApiResponse::class);
    }

    public function boot(): void
    {
        //
    }
}

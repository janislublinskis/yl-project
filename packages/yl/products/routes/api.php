<?php

use Illuminate\Support\Facades\Route;
use Yl\Products\Http\Controllers\ProductController;

/*
 * YL Products Module — API Routes
 *
 * All routes are prefixed with /api/products and use JSON responses.
 *
 * Registered by ProductsServiceProvider::boot() via loadRoutesFrom().
 *
 * Endpoints:
 *   GET    /api/products          List products (paginated, filterable)
 *   POST   /api/products          Create a product (dispatches export job)
 *   GET    /api/products/{id}     Retrieve a product
 *   PUT    /api/products/{id}     Update a product
 *   DELETE /api/products/{id}     Soft-delete a product
 */
Route::prefix('api/products')
    ->middleware('api')
    ->group(function () {
        Route::get('/',        [ProductController::class, 'index']);
        Route::post('/',       [ProductController::class, 'store']);
        Route::get('/{id}',    [ProductController::class, 'show']);
        Route::put('/{id}',    [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
    });

<?php

use Illuminate\Support\Facades\Route;
use Yl\Posts\Http\Controllers\PostController;

/*
 * YL Posts Module — API Routes
 *
 * Registered by PostsServiceProvider::boot() via loadRoutesFrom().
 *
 * Endpoints:
 *   GET    /api/posts          List posts (paginated, filterable by status)
 *   POST   /api/posts          Create a post (dispatches publish job if published)
 *   GET    /api/posts/{id}     Retrieve a single post
 *   PUT    /api/posts/{id}     Update a post (dispatches job on publish transition)
 *   DELETE /api/posts/{id}     Soft-delete a post
 */
Route::prefix('api/posts')
    ->middleware('api')
    ->group(function () {
        Route::get('/',        [PostController::class, 'index']);
        Route::post('/',       [PostController::class, 'store']);
        Route::get('/{id}',    [PostController::class, 'show']);
        Route::put('/{id}',    [PostController::class, 'update']);
        Route::delete('/{id}', [PostController::class, 'destroy']);
    });

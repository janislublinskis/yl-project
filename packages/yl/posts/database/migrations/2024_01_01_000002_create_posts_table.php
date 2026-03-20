<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `posts` table for the YL Posts module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            $table->string('title', 255);

            // Unique slug — used as the public URL path segment.
            $table->string('slug', 255)->unique();

            // Post body content — no size limit.
            $table->longText('body');

            $table->enum('status', ['draft', 'published', 'archived'])
                  ->default('draft');

            // Null until the post is explicitly published.
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

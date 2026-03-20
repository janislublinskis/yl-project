<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `products` table for the YL Products module.
 *
 * The module owns this migration — it is auto-loaded by ProductsServiceProvider
 * via loadMigrationsFrom(), so the host application does not need to copy
 * or publish it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('name', 255);

            // Optional long-form description; nullable so minimal products
            // can be created without it.
            $table->text('description')->nullable();

            // DECIMAL preserves monetary precision; float would introduce
            // floating-point rounding errors.
            $table->decimal('price', 10, 2);

            $table->unsignedInteger('stock')->default(0);

            // Enum-style status — constrained to three known values.
            $table->enum('status', ['active', 'inactive', 'archived'])
                  ->default('active');

            // Standard Laravel timestamps + soft-delete column.
            $table->timestamps();
            $table->softDeletes();

            // Index status for the common `scopeActive()` query.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

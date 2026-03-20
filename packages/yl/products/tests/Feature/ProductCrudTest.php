<?php

namespace Yl\Products\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Yl\Products\Jobs\ProcessProductExportJob;
use Yl\Products\Models\Product;
use Yl\Products\Tests\TestCase;

/**
 * ProductCrudTest
 *
 * Feature tests covering all five CRUD endpoints.
 * Uses RefreshDatabase so each test starts with a clean slate.
 *
 * Run with:
 *   php artisan test  (from inside the container)
 */
class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    // ── index ──────────────────────────────────────────────────────

    /** @test */
    public function it_returns_a_paginated_list_of_products(): void
    {
        Product::factory()->count(5)->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [['id', 'name', 'price', 'stock', 'status']],
                ],
            ])
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function it_filters_products_by_status(): void
    {
        Product::factory()->active()->count(3)->create();
        Product::factory()->inactive()->count(2)->create();

        $this->getJson('/api/products?status=active')
            ->assertOk()
            ->assertJsonPath('data.total', 3);
    }

    // ── store ──────────────────────────────────────────────────────

    /** @test */
    public function it_creates_a_product_and_dispatches_export_job(): void
    {
        // Fake the queue so the job is captured, not actually executed.
        Queue::fake();

        $payload = [
            'name'  => 'Test Widget',
            'price' => 1999,
            'stock' => 100,
        ];

        $this->postJson('/api/products', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Test Widget');

        // Verify the product was persisted.
        $this->assertDatabaseHas('products', ['name' => 'Test Widget']);

        // Verify the async job was dispatched to the queue.
        Queue::assertPushed(ProcessProductExportJob::class, function ($job) {
            return $job->product->name === 'Test Widget';
        });
    }

    /** @test */
    public function it_returns_422_when_required_fields_are_missing(): void
    {
        $this->postJson('/api/products', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors']);
    }

    /** @test */
    public function it_rejects_a_negative_price(): void
    {
        $this->postJson('/api/products', [
            'name'  => 'Bad Product',
            'price' => -5,
            'stock' => 10,
        ])->assertUnprocessable();
    }

    // ── show ───────────────────────────────────────────────────────

    /** @test */
    public function it_returns_a_single_product(): void
    {
        $product = Product::factory()->create(['name' => 'Findable Product']);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Findable Product');
    }

    /** @test */
    public function it_returns_404_for_a_missing_product(): void
    {
        $this->getJson('/api/products/99999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ── update ─────────────────────────────────────────────────────

    /** @test */
    public function it_updates_a_product(): void
    {
        $product = Product::factory()->create(['price' => 1000]);

        $this->putJson("/api/products/{$product->id}", ['price' => 2500])
            ->assertOk()
            ->assertJsonPath('data.price', 2500);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 2500]);
    }

    /** @test */
    public function it_returns_404_when_updating_missing_product(): void
    {
        $this->putJson('/api/products/99999', ['price' => 500])
            ->assertNotFound();
    }

    // ── destroy ────────────────────────────────────────────────────

    /** @test */
    public function it_soft_deletes_a_product(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        // Soft-deleted: not found via normal query, but exists in DB.
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /** @test */
    public function it_returns_404_when_deleting_missing_product(): void
    {
        $this->deleteJson('/api/products/99999')
            ->assertNotFound();
    }
}

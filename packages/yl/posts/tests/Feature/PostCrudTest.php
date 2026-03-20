<?php

namespace Yl\Posts\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Yl\Posts\Jobs\ProcessPostPublishedJob;
use Yl\Posts\Models\Post;
use Yl\Posts\Tests\TestCase;

/**
 * PostCrudTest
 *
 * Feature tests covering all five CRUD endpoints of the Posts module.
 *
 * Run with:
 *   php artisan test  (from inside any container)
 */
class PostCrudTest extends TestCase
{
    use RefreshDatabase;

    // ── index ──────────────────────────────────────────────────────

    /** @test */
    public function it_returns_a_paginated_list_of_posts(): void
    {
        Post::factory()->count(5)->create();

        $this->getJson('/api/posts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'data' => [['id', 'title', 'slug', 'status']],
                ],
            ]);
    }

    /** @test */
    public function it_filters_posts_by_status(): void
    {
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(4)->create();

        $this->getJson('/api/posts?status=draft')
            ->assertOk()
            ->assertJsonPath('data.total', 4);
    }

    // ── store ──────────────────────────────────────────────────────

    /** @test */
    public function it_creates_a_draft_post_without_dispatching_a_job(): void
    {
        Queue::fake();

        $this->postJson('/api/posts', [
            'title'  => 'My Draft Post',
            'body'   => 'Draft content here.',
            'status' => 'draft',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        // No job should be dispatched for a draft.
        Queue::assertNotPushed(ProcessPostPublishedJob::class);
    }

    /** @test */
    public function it_creates_a_published_post_and_dispatches_the_publish_job(): void
    {
        Queue::fake();

        $this->postJson('/api/posts', [
            'title'        => 'My Published Post',
            'body'         => 'Published content here.',
            'status'       => 'published',
            'published_at' => now()->toDateTimeString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('posts', ['title' => 'My Published Post']);

        // Job MUST be dispatched when status is published.
        Queue::assertPushed(ProcessPostPublishedJob::class, function ($job) {
            return $job->post->title === 'My Published Post';
        });
    }

    /** @test */
    public function it_auto_generates_a_slug_from_the_title(): void
    {
        Queue::fake();

        $this->postJson('/api/posts', [
            'title' => 'My Awesome Article',
            'body'  => 'Body content.',
        ])->assertCreated();

        $this->assertDatabaseHas('posts', ['slug' => 'my-awesome-article']);
    }

    /** @test */
    public function it_returns_422_when_required_fields_are_missing(): void
    {
        $this->postJson('/api/posts', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function it_rejects_a_duplicate_slug(): void
    {
        Post::factory()->create(['slug' => 'taken-slug']);

        $this->postJson('/api/posts', [
            'title' => 'Another Post',
            'body'  => 'Body.',
            'slug'  => 'taken-slug',
        ])->assertUnprocessable();
    }

    // ── show ───────────────────────────────────────────────────────

    /** @test */
    public function it_returns_a_single_post(): void
    {
        $post = Post::factory()->create(['title' => 'Findable Post']);

        $this->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Findable Post');
    }

    /** @test */
    public function it_returns_404_for_a_missing_post(): void
    {
        $this->getJson('/api/posts/99999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ── update ─────────────────────────────────────────────────────

    /** @test */
    public function it_updates_a_post(): void
    {
        $post = Post::factory()->draft()->create();

        $this->putJson("/api/posts/{$post->id}", ['title' => 'Updated Title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'Updated Title']);
    }

    /** @test */
    public function it_dispatches_publish_job_only_on_status_transition(): void
    {
        Queue::fake();

        // Start as draft, then publish via update.
        $post = Post::factory()->draft()->create();

        $this->putJson("/api/posts/{$post->id}", [
            'status'       => 'published',
            'published_at' => now()->toDateTimeString(),
        ])->assertOk();

        // Job dispatched on the draft → published transition.
        Queue::assertPushed(ProcessPostPublishedJob::class);
    }

    /** @test */
    public function it_does_not_dispatch_job_when_post_stays_published(): void
    {
        Queue::fake();

        // Already published — updating the title should NOT re-dispatch.
        $post = Post::factory()->published()->create();

        $this->putJson("/api/posts/{$post->id}", ['title' => 'New Title'])
            ->assertOk();

        Queue::assertNotPushed(ProcessPostPublishedJob::class);
    }

    /** @test */
    public function it_returns_404_when_updating_a_missing_post(): void
    {
        $this->putJson('/api/posts/99999', ['title' => 'X'])
            ->assertNotFound();
    }

    // ── destroy ────────────────────────────────────────────────────

    /** @test */
    public function it_soft_deletes_a_post(): void
    {
        $post = Post::factory()->create();

        $this->deleteJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    /** @test */
    public function it_returns_404_when_deleting_a_missing_post(): void
    {
        $this->deleteJson('/api/posts/99999')
            ->assertNotFound();
    }
}

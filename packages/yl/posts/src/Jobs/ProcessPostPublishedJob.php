<?php

namespace Yl\Posts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Yl\Posts\Models\Post;

/**
 * ProcessPostPublishedJob
 *
 * Dispatched when a Post transitions to 'published' status.
 * Sent to the RabbitMQ queue and processed by the worker container.
 *
 * What it does:
 *   1. Pings a search indexer webhook (HTTP request — requirement 8)
 *   2. Runs a shell command to flush the page cache (shell command — requirement 8)
 *
 * Retry strategy:
 *   - Up to 3 attempts
 *   - 60s timeout per attempt
 *
 * Dispatch:
 *   ProcessPostPublishedJob::dispatch($post);
 */
class ProcessPostPublishedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    /**
     * SerializesModels re-fetches the Post from the DB when the worker
     * picks up the job, so we always act on the latest persisted state.
     */
    public function __construct(public readonly Post $post)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ProcessPostPublishedJob started', [
            'post_id' => $this->post->id,
            'slug'    => $this->post->slug,
        ]);

        $this->notifySearchIndexer();
        $this->flushPageCache();

        Log::info('ProcessPostPublishedJob completed', ['post_id' => $this->post->id]);
    }

    /**
     * Notify an external search indexer that new content is available.
     *
     * Requirement 8: "make an HTTP request"
     *
     * In production this would call Algolia, Meilisearch, Elasticsearch,
     * or any custom indexer. Here we POST to httpbin.org for demonstration.
     */
    private function notifySearchIndexer(): void
    {
        $indexerUrl = config('posts.indexer_url', 'https://httpbin.org/post');

        try {
            $response = Http::timeout(30)->post($indexerUrl, [
                'event'        => 'post.published',
                'post_id'      => $this->post->id,
                'title'        => $this->post->title,
                'slug'         => $this->post->slug,
                'published_at' => $this->post->published_at?->toIso8601String(),
                'timestamp'    => now()->toIso8601String(),
            ]);

            Log::info('Search indexer notified', [
                'post_id' => $this->post->id,
                'status'  => $response->status(),
            ]);
        } catch (\Exception $e) {
            // Log the failure without rethrowing — a failed indexer ping
            // should not roll back the publication or clog the queue.
            Log::warning('Search indexer notification failed', [
                'post_id' => $this->post->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Flush the page cache for the newly published post.
     *
     * Requirement 8: "perform a shell command"
     *
     * In production this might call `nginx -s reload`, a Varnish purge,
     * or a CDN invalidation CLI. Here we write a log entry safely.
     */
    private function flushPageCache(): void
    {
        $slug    = escapeshellarg($this->post->slug);
        $timestamp = escapeshellarg(now()->toIso8601String());

        // escapeshellarg ensures the slug cannot be used for command injection.
        $command = "echo \"Cache flushed for post {$slug} at {$timestamp}\" "
                 . '>> /var/www/html/storage/logs/cache.log';

        shell_exec($command);

        Log::info('Page cache flush command executed', ['post_id' => $this->post->id]);
    }

    /**
     * Called after all retry attempts are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessPostPublishedJob failed permanently', [
            'post_id' => $this->post->id,
            'error'   => $exception->getMessage(),
        ]);
    }
}

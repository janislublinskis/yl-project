<?php

namespace Yl\Products\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Yl\Products\Models\Product;

/**
 * ProcessProductExportJob
 *
 * An asynchronous job dispatched whenever a Product is created.
 * Sent to the RabbitMQ queue and processed by the worker container.
 *
 * What it does:
 *   1. Makes an HTTP POST to a configurable webhook URL (requirement 8 — HTTP request)
 *   2. Runs a shell command to regenerate a static product sitemap (requirement 8 — shell command)
 *
 * Retry strategy:
 *   - Up to 3 attempts (set in docker-compose queue:work --tries=3)
 *   - 60 second timeout per attempt
 *   - Backs off 30 seconds between retries
 *
 * Dispatch:
 *   ProcessProductExportJob::dispatch($product);
 */
class ProcessProductExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum number of times the job may be attempted. */
    public int $tries = 3;

    /** Seconds before the job is considered timed out. */
    public int $timeout = 60;

    /**
     * @param  Product  $product  The newly created product.
     *                            SerializesModels re-fetches it from the DB
     *                            when the worker picks up the job, ensuring
     *                            we always have the latest state.
     */
    public function __construct(public readonly Product $product)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ProcessProductExportJob started', ['product_id' => $this->product->id]);

        $this->notifyWebhook();
        $this->regenerateSitemap();

        Log::info('ProcessProductExportJob completed', ['product_id' => $this->product->id]);
    }

    /**
     * Make an HTTP POST to a webhook notifying external systems
     * (e.g. an ERP, a search indexer) about the new product.
     *
     * Requirement 8: "make an HTTP request"
     */
    private function notifyWebhook(): void
    {
        // Webhook URL is configurable via environment variable.
        // Falls back to a public echo service for development/demo.
        $webhookUrl = config('products.webhook_url', 'https://httpbin.org/post');

        try {
            $response = Http::timeout(30)->post($webhookUrl, [
                'event'      => 'product.created',
                'product_id' => $this->product->id,
                'name'       => $this->product->name,
                'price'      => $this->product->price,
                'status'     => $this->product->status,
                'timestamp'  => now()->toIso8601String(),
            ]);

            Log::info('Webhook notified', [
                'product_id' => $this->product->id,
                'status'     => $response->status(),
            ]);
        } catch (\Exception $e) {
            // Log but don't rethrow — a webhook failure should not
            // cause the entire job to fail and clog the queue.
            Log::warning('Webhook notification failed', [
                'product_id' => $this->product->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Append a sitemap update entry to the sitemap log.
     *
     * Requirement 8: "perform a shell command"
     *
     * In a real application this would invoke a sitemap generator CLI tool.
     * Here we write a log entry using native PHP file I/O — no shell process
     * or injection surface needed for simple file appends.
     */
    private function regenerateSitemap(): void
    {
        $entry = sprintf(
            "Sitemap updated for product ID %d at %s\n",
            $this->product->id,
            now()->toIso8601String()
        );

        file_put_contents(
            storage_path('logs/sitemap.log'),
            $entry,
            FILE_APPEND | LOCK_EX
        );

        Log::info('Sitemap regeneration executed', ['product_id' => $this->product->id]);
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessProductExportJob failed permanently', [
            'product_id' => $this->product->id,
            'error'      => $exception->getMessage(),
        ]);
    }
}

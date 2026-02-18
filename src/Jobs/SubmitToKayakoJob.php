<?php

namespace Acoustica\StatamicSupport\Jobs;

use Acoustica\StatamicSupport\Contracts\SupportProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Asset;

class SubmitToKayakoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [30, 60, 300, 900, 3600];

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $data
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SupportProvider $provider): void
    {
        if (!$provider->isConfigured()) {
            Log::warning('Support: Kayako provider not configured, skipping queued submission', [
                'email' => $this->data['email'] ?? 'unknown',
            ]);
            return;
        }

        try {
            $data = $this->data;
            $data['resolved_attachments'] = $this->resolveAttachments($data['attachments'] ?? []);

            $response = $provider->createCase($data);

            Log::info('Support: case created via queue', [
                'provider' => $provider->getName(),
                'case_id' => $response['id'] ?? 'unknown',
                'email' => $this->data['email'],
                'attachments' => count($data['resolved_attachments']),
            ]);

            $this->cleanupAttachments($data['attachments'] ?? []);
        } catch (\Exception $e) {
            Log::error('Support: queued job failed to create case', [
                'provider' => $provider->getName(),
                'error' => $e->getMessage(),
                'email' => $this->data['email'],
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Resolve asset IDs to file paths, filenames, and MIME types.
     */
    protected function resolveAttachments(array $assetIds): array
    {
        $resolved = [];

        foreach ($assetIds as $assetId) {
            $asset = Asset::find($assetId);

            if (!$asset) {
                Log::warning('Support: attachment asset not found', ['asset_id' => $assetId]);
                continue;
            }

            $resolved[] = [
                'path' => $asset->resolvedPath(),
                'filename' => $asset->basename(),
                'mime_type' => $asset->mimeType(),
                'asset_id' => $assetId,
            ];
        }

        return $resolved;
    }

    /**
     * Delete local assets after successful upload to the provider.
     */
    protected function cleanupAttachments(array $assetIds): void
    {
        if (!config('support.attachments.cleanup_after_upload', true)) {
            return;
        }

        foreach ($assetIds as $assetId) {
            $asset = Asset::find($assetId);

            if ($asset) {
                $asset->delete();
                Log::debug('Support: cleaned up attachment asset', ['asset_id' => $assetId]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::critical('Support: all retry attempts exhausted for Kayako submission', [
            'email' => $this->data['email'] ?? 'unknown',
            'subject' => $this->data['subject'] ?? 'unknown',
            'error' => $exception?->getMessage(),
        ]);

        // TODO: Consider sending an alert or storing in a failed_submissions table
    }
}

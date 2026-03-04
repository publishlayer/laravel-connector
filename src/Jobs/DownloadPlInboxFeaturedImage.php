<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PublishLayer\LaravelConnector\Models\PlInboxDraft;
use PublishLayer\LaravelConnector\Services\ImageDownloadService;

class DownloadPlInboxFeaturedImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 120;

    public function __construct(
        public readonly int $draftId
    ) {
    }

    public function handle(ImageDownloadService $imageService): void
    {
        $draft = PlInboxDraft::query()->find($this->draftId);

        if (! $draft) {
            return;
        }

        $url = is_string($draft->featured_image_url) ? trim($draft->featured_image_url) : '';
        if ($url === '') {
            return;
        }

        if (is_string($draft->featured_image_path) && $draft->featured_image_path !== '' && $imageService->exists($draft->featured_image_path)) {
            return;
        }

        $storagePath = sprintf(
            '%s/%s/%s',
            trim((string) config('publishlayer_inbox.images.path_prefix', 'pl-inbox'), '/'),
            $draft->site_key,
            $draft->pl_draft_id
        );

        $result = $imageService->download($url, $storagePath, 'featured');

        $draft->update([
            'featured_image_path' => $result['path'],
        ]);
    }

    public function queue(): string
    {
        return (string) config('publishlayer_connector.webhooks.queue', 'default');
    }
}

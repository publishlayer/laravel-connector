<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Events;

use PublishLayer\LaravelConnector\Models\PublishLayerDraft;

class DraftImageDownloaded
{
    public function __construct(
        public readonly PublishLayerDraft $draft
    ) {
    }
}

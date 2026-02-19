<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Events;

class PublishLayerWebhookReceived
{
    /**
     * @param array<mixed> $payload
     * @param array<string, string|null> $headers
     */
    public function __construct(
        public readonly array $payload,
        public readonly ?string $eventId,
        public readonly ?string $type,
        public readonly array $headers = []
    ) {
    }
}

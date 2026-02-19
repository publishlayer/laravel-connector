<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Events;

class DraftReady
{
    /**
     * @param array<mixed> $payload
     */
    public function __construct(public readonly array $payload)
    {
    }
}

<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Contracts;

interface PublishLayerClientContract
{
    public function ping(): bool;

    /**
     * @return array<string, mixed>
     */
    public function health(): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createBrief(array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createDraft(array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function listSites(): array;
}

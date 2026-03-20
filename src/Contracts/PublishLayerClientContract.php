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

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function getContentMarkdown(string $siteId, string $contentId, array $query = []): array;

    /**
     * Register a webhook endpoint.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function registerWebhook(array $payload): array;

    /**
     * Send heartbeat to PublishLayer.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function heartbeat(array $payload = []): array;
}

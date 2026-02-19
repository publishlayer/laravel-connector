<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Client;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

class PublishLayerClient
{
    /**
     * @param array{api_key?: string|null, base_url?: string|null, workspace_id?: string|null} $connection
     * @param array{timeout_seconds?: int, retries?: int, retry_sleep_ms?: int} $httpConfig
     */
    public function __construct(
        private readonly Factory $http,
        private readonly array $connection,
        private readonly array $httpConfig
    ) {
    }

    public function ping(): bool
    {
        $response = $this->request()->get('/v1/ping');

        return $response->status() === 200;
    }

    private function request(): PendingRequest
    {
        $request = $this->http
            ->baseUrl((string) ($this->connection['base_url'] ?? 'https://api.publishlayer.com'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->httpConfig['timeout_seconds'] ?? 10))
            ->retry(
                (int) ($this->httpConfig['retries'] ?? 2),
                (int) ($this->httpConfig['retry_sleep_ms'] ?? 200)
            );

        if (! empty($this->connection['api_key'])) {
            $request = $request->withToken((string) $this->connection['api_key']);
        }

        if (! empty($this->connection['workspace_id'])) {
            $request = $request->withHeaders([
                'X-PublishLayer-Workspace' => (string) $this->connection['workspace_id'],
            ]);
        }

        return $request;
    }
}

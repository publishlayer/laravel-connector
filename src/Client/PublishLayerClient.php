<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Client;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use PublishLayer\LaravelConnector\Contracts\PublishLayerClientContract;
use PublishLayer\LaravelConnector\Exceptions\PublishLayerRequestException;

class PublishLayerClient implements PublishLayerClientContract
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

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return $this->send('GET', '/v1/health');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createBrief(array $payload): array
    {
        return $this->send('POST', '/v1/briefs', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createDraft(array $payload): array
    {
        return $this->send('POST', '/v1/drafts', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function listSites(): array
    {
        return $this->send('GET', '/v1/sites');
    }

    /**
     * Get a draft by ID.
     *
     * @return array<string, mixed>
     */
    public function getDraft(string $draftId): array
    {
        return $this->send('GET', '/v1/drafts/' . $draftId);
    }

    /**
     * List drafts.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function listDrafts(array $query = []): array
    {
        return $this->send('GET', '/v1/drafts', $query);
    }

    /**
     * Register a webhook endpoint.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function registerWebhook(array $payload): array
    {
        return $this->sendAdmin('POST', '/v1/clients/webhooks', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $uri, array $payload = []): array
    {
        $request = $this->request();
        $response = $method === 'GET'
            ? $request->get($uri, $payload)
            : $request->send($method, $uri, ['json' => $payload]);

        return $this->decode($response);
    }

    /**
     * Send request using admin key header (for webhook registration).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sendAdmin(string $method, string $uri, array $payload = []): array
    {
        $request = $this->adminRequest();
        $response = $method === 'GET'
            ? $request->get($uri, $payload)
            : $request->send($method, $uri, ['json' => $payload]);

        return $this->decode($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if ($response->failed()) {
            throw PublishLayerRequestException::fromResponse($response);
        }

        /** @var mixed $decoded */
        $decoded = $response->json();

        if (is_array($decoded)) {
            return $decoded;
        }

        return ['data' => $decoded];
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
                (int) ($this->httpConfig['retry_sleep_ms'] ?? 200),
                throw: false
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

    /**
     * Build request with admin key header for privileged operations.
     */
    private function adminRequest(): PendingRequest
    {
        $request = $this->http
            ->baseUrl((string) ($this->connection['base_url'] ?? 'https://api.publishlayer.com'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->httpConfig['timeout_seconds'] ?? 10))
            ->retry(
                (int) ($this->httpConfig['retries'] ?? 2),
                (int) ($this->httpConfig['retry_sleep_ms'] ?? 200),
                throw: false
            );

        // Admin endpoints use X-Admin-Key header
        if (! empty($this->connection['api_key'])) {
            $request = $request->withHeaders([
                'X-Admin-Key' => (string) $this->connection['api_key'],
            ]);
        }

        return $request;
    }
}

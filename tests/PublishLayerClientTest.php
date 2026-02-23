<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\Facades\Http;
use PublishLayer\LaravelConnector\Client\PublishLayerClient;
use PublishLayer\LaravelConnector\Contracts\PublishLayerClientContract;
use PublishLayer\LaravelConnector\Exceptions\PublishLayerRequestException;

class PublishLayerClientTest extends TestCase
{
    public function test_ping_returns_true_on_200_response(): void
    {
        Http::fake([
            'https://api.publishlayer.com/v1/ping' => Http::response(['ok' => true], 200),
        ]);

        $client = $this->app->make(PublishLayerClient::class);

        self::assertTrue($client->ping());
    }

    public function test_service_provider_registers_client_aliases(): void
    {
        self::assertInstanceOf(PublishLayerClient::class, $this->app->make(PublishLayerClientContract::class));
        self::assertInstanceOf(PublishLayerClient::class, $this->app->make('publishlayer'));
    }

    public function test_config_is_merged_with_default_values(): void
    {
        self::assertSame('https://api.publishlayer.com', config('publishlayer_connector.base_url'));
        self::assertSame(10, config('publishlayer_connector.timeout'));
        self::assertSame('publishlayer/webhook', config('publishlayer_connector.webhooks.path'));
    }

    public function test_client_sends_expected_headers_and_base_url(): void
    {
        Http::fake([
            'https://api.publishlayer.com/v1/sites' => Http::response(['data' => []], 200),
        ]);

        $client = $this->app->make(PublishLayerClient::class);
        $client->listSites();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.publishlayer.com/v1/sites'
                && $request->hasHeader('Authorization', 'Bearer test-api-key')
                && $request->hasHeader('X-PublishLayer-Workspace', 'workspace-test')
                && $request->hasHeader('Accept', 'application/json');
        });
    }

    public function test_client_throws_readable_exception_for_non_2xx_response(): void
    {
        Http::fake([
            'https://api.publishlayer.com/v1/drafts' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $client = $this->app->make(PublishLayerClient::class);

        $this->expectException(PublishLayerRequestException::class);
        $this->expectExceptionMessage('PublishLayer API request failed (401): Unauthorized');

        $client->createDraft(['title' => 'Test Draft']);
    }
}

<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\Facades\Http;
use PublishLayer\LaravelConnector\Client\PublishLayerClient;

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
}

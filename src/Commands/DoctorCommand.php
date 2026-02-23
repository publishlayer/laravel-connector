<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Commands;

use Illuminate\Console\Command;
use PublishLayer\LaravelConnector\Client\PublishLayerClient;
use Throwable;

class DoctorCommand extends Command
{
    protected $signature = 'publishlayer:doctor';

    protected $description = 'Run diagnostics for PublishLayer connector configuration';

    public function handle(PublishLayerClient $client): int
    {
        $apiKey = (string) config('publishlayer_connector.connections.default.api_key', '');
        $webhookSecret = (string) config('publishlayer_connector.webhooks.signing_secret', '');

        $this->line('base_url: ' . (string) config('publishlayer_connector.connections.default.base_url'));
        $this->line('webhook_path: ' . (string) config('publishlayer_connector.webhooks.path'));

        $this->line('api_key: ' . ($apiKey !== '' ? 'ok' : 'missing'));
        $this->line('webhook_secret: ' . ($webhookSecret !== '' ? 'ok' : 'missing'));

        try {
            $pingOk = $client->ping();
            $this->line('ping: ' . ($pingOk ? 'ok' : 'fail'));
        } catch (Throwable) {
            $this->line('ping: fail');
        }

        return self::SUCCESS;
    }
}

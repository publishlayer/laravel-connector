<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Commands;

use Illuminate\Console\Command;
use PublishLayer\LaravelConnector\Contracts\PublishLayerClientContract;

class RegisterWebhooksCommand extends Command
{
    protected $signature = 'publishlayer:webhooks:register
                            {--force : Re-register even if already registered}
                            {--url= : Override the webhook URL}';

    protected $description = 'Register webhook endpoints with PublishLayer';

    public function handle(PublishLayerClientContract $client): int
    {
        $publicUrl = $this->option('url')
            ?? config('publishlayer_connector.webhooks.public_url');

        if (empty($publicUrl)) {
            $this->error('Webhook public URL is not configured.');
            $this->line('Set PUBLISHLAYER_CONNECTOR_PUBLIC_URL in your .env file or use --url option.');

            return self::FAILURE;
        }

        $webhookPath = config('publishlayer_connector.webhooks.path', 'publishlayer/webhook');
        $fullUrl = rtrim($publicUrl, '/') . '/' . ltrim($webhookPath, '/');

        $clientSiteId = config('publishlayer_connector.client_site_id');
        if (empty($clientSiteId)) {
            $this->error('Client site ID is not configured.');
            $this->line('Set PUBLISHLAYER_CLIENT_SITE_ID in your .env file.');

            return self::FAILURE;
        }

        $secret = config('publishlayer_connector.webhooks.signing_secret');
        if (empty($secret)) {
            $this->warn('Webhook signing secret is not configured. Generating a random one...');
            $secret = bin2hex(random_bytes(24));
            $this->info("Generated secret: {$secret}");
            $this->line('Add this to your .env: PUBLISHLAYER_WEBHOOK_SECRET=' . $secret);
            $this->newLine();
        }

        $this->info("Registering webhook endpoint: {$fullUrl}");

        try {
            $result = $client->registerWebhook([
                'client_site_id' => $clientSiteId,
                'event_type' => 'draft.ready',
                'url' => $fullUrl,
                'signing' => [
                    'method' => 'hmac_sha256',
                    'secret' => $secret,
                ],
            ]);

            if (! empty($result['ok'])) {
                $this->info('Webhook registered successfully!');

                if (! empty($result['id'])) {
                    $this->line('Webhook ID: ' . $result['id']);
                }

                if (! empty($result['secret']) && $result['secret'] !== $secret) {
                    $this->warn('Server returned a different secret. Update your .env:');
                    $this->line('PUBLISHLAYER_WEBHOOK_SECRET=' . $result['secret']);
                }

                return self::SUCCESS;
            }

            $this->error('Failed to register webhook.');
            $this->line('Response: ' . json_encode($result));

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Error registering webhook: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}

<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use PublishLayer\LaravelConnector\Contracts\PublishLayerClientContract;
use PublishLayer\LaravelConnector\Models\PublishLayerConnectorHeartbeat;

class HeartbeatCommand extends Command
{
    protected $signature = 'publishlayer:heartbeat {--site-key= : Site key used for local connector heartbeat tracking}';

    protected $description = 'Send heartbeat to PublishLayer to update site connectivity status';

    public function handle(PublishLayerClientContract $client): int
    {
        $siteKey = trim((string) ($this->option('site-key') ?? config('publishlayer_connector.site_key', 'default')));
        if ($siteKey === '') {
            $siteKey = 'default';
        }

        if (Schema::hasTable('publishlayer_connector_heartbeats')) {
            PublishLayerConnectorHeartbeat::updateOrCreate(
                ['site_key' => mb_substr($siteKey, 0, 128)],
                [
                    'last_seen_at' => now(),
                    'source' => 'command',
                    'meta' => [
                        'command' => 'publishlayer:heartbeat',
                    ],
                ]
            );
        }

        $payload = [
            'platform' => 'laravel',
            'connector_version' => '0.1.0',
            'framework_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'app_url' => config('app.url'),
            'site_key' => $siteKey,
            'capabilities' => [
                'draft_ready_webhook',
                'image_download',
            ],
        ];

        $this->info('Sending heartbeat to PublishLayer...');

        try {
            $result = $client->heartbeat($payload);

            if (! empty($result['ok'])) {
                $this->info('Heartbeat sent successfully!');
                $this->line('  Site key: ' . $siteKey);
                $this->line('  Site ID: ' . ($result['site_id'] ?? 'unknown'));
                $this->line('  Server time: ' . ($result['server_time'] ?? 'unknown'));
                $this->line('  Next heartbeat in: ' . ($result['next_recommended_heartbeat_seconds'] ?? 300) . ' seconds');

                return self::SUCCESS;
            }

            $this->error('Heartbeat failed: ' . ($result['error'] ?? 'Unknown error'));

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Heartbeat failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}

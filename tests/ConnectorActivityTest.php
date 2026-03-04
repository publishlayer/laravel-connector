<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerConnectorHeartbeat;
use PublishLayer\LaravelConnector\Models\PublishLayerFailedMessage;
use PublishLayer\LaravelConnector\Models\PublishLayerWebhookEvent;

class ConnectorActivityTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_activity_check_returns_200_for_valid_site_key(): void
    {
        $event = PublishLayerWebhookEvent::create([
            'site_key' => 'site_valid',
            'event_id' => 'evt_activity_ok',
            'event_type' => 'draft.ready',
            'payload' => ['id' => 'evt_activity_ok'],
            'status' => PublishLayerWebhookEvent::STATUS_PROCESSED,
            'received_at' => now()->subHour(),
            'processed_at' => now()->subMinutes(30),
        ]);

        PublishLayerConnectorHeartbeat::create([
            'site_key' => 'site_valid',
            'last_seen_at' => now()->subMinutes(5),
            'source' => 'test',
            'meta' => ['source' => 'phpunit'],
        ]);

        PublishLayerFailedMessage::create([
            'webhook_event_id' => $event->id,
            'site_key' => 'site_valid',
            'event_id' => 'evt_activity_ok',
            'error_class' => 'RuntimeException',
            'error_message' => 'Test failure',
            'payload' => ['id' => 'evt_activity_ok'],
            'context' => ['test' => true],
            'failed_at' => now()->subMinutes(10),
        ]);

        $response = $this->getJson('/publishlayer/connector/activity?site_key=site_valid');

        $response->assertOk()->assertJson([
            'recent_events_count_24h' => 1,
            'failed_events_count_24h' => 1,
        ])->assertJsonStructure([
            'last_webhook_received_at',
            'last_processed_at',
            'last_heartbeat_at',
            'recent_events_count_24h',
            'failed_events_count_24h',
        ]);
    }

    public function test_activity_check_returns_422_for_invalid_payload(): void
    {
        $this->postJson('/publishlayer/connector/activity', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['site_key']);

        $this->postJson('/publishlayer/connector/activity', ['site_key' => 'site_unknown'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['site_key']);
    }

    public function test_activity_check_accepts_configured_api_key_as_site_key(): void
    {
        config()->set('publishlayer_connector.site_key', null);
        config()->set('publishlayer_connector.api_key', 'pl_site_api_key_123');
        config()->set('publishlayer_connector.connections.default.api_key', 'pl_site_api_key_123');

        $this->postJson('/publishlayer/connector/activity', [
            'site_key' => 'pl_site_api_key_123',
        ])->assertOk();
    }
}

<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

class HealthEndpointTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_health_endpoint_returns_summary_for_authorized_requests(): void
    {
        $this->withHeaders([
            'X-PublishLayer-Key' => 'test-sync-key',
            'X-PublishLayer-Site' => 'site-test',
        ])->getJson('/api/publishlayer/health')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'checks',
                'latest_sync_log',
            ]);
    }

    public function test_health_endpoint_rejects_mismatched_site_identifier(): void
    {
        $this->withHeaders([
            'X-PublishLayer-Key' => 'test-sync-key',
            'X-PublishLayer-Site' => 'wrong-site',
        ])->getJson('/api/publishlayer/health')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['site_id']);
    }
}

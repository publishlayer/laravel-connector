<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

class InstallCommandTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_install_command_reports_checks_and_next_steps(): void
    {
        $this->artisan('publishlayer:install')
            ->expectsOutputToContain('[PASS] enabled')
            ->expectsOutputToContain('[PASS] app_key')
            ->expectsOutputToContain('[PASS] database.webhooks')
            ->expectsOutputToContain('Next steps')
            ->expectsOutputToContain('/api/publishlayer/sync')
            ->assertExitCode(0);
    }
}

<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

class HealthCheckCommandTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_health_check_command_reports_pass_and_warn_states(): void
    {
        $this->artisan('publishlayer:health-check')
            ->expectsOutputToContain('[PASS] enabled')
            ->expectsOutputToContain('[PASS] database.tables')
            ->expectsOutputToContain('[WARN] sync.latest')
            ->assertExitCode(0);
    }
}

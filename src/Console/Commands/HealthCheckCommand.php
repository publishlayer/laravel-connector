<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Console\Commands;

use Illuminate\Console\Command;
use PublishLayer\LaravelConnector\Services\ConnectorHealthService;

class HealthCheckCommand extends Command
{
    protected $signature = 'publishlayer:health-check';

    protected $description = 'Run health checks for the PublishLayer connector';

    public function handle(ConnectorHealthService $healthService): int
    {
        $summary = $healthService->summary();

        $this->info('PublishLayer connector health');
        foreach ($summary['checks'] as $check) {
            $this->renderCheck(
                (string) $check['status'],
                (string) $check['key'],
                (string) $check['message']
            );
        }

        $this->newLine();
        $this->line('Mode: '.(string) $summary['mode']);

        if ($summary['latest_sync_log'] === null) {
            $this->renderCheck('warn', 'sync.latest', 'No sync log entries recorded yet.');
        } else {
            $latest = $summary['latest_sync_log'];

            $this->renderCheck(
                (string) ($latest['status'] === 'success' ? 'pass' : 'warn'),
                'sync.latest',
                sprintf(
                    'Latest sync %s for %s at %s.',
                    (string) $latest['status'],
                    (string) ($latest['source_id'] ?: 'unknown source'),
                    (string) ($latest['synced_at'] ?: $latest['created_at'] ?: 'unknown time')
                )
            );
        }

        return $summary['ok'] ? self::SUCCESS : self::FAILURE;
    }

    private function renderCheck(string $status, string $key, string $message): void
    {
        $label = match ($status) {
            'pass' => 'PASS',
            'warn' => 'WARN',
            default => 'FAIL',
        };

        $line = sprintf('[%s] %s - %s', $label, $key, $message);

        match ($status) {
            'pass' => $this->line($line),
            'warn' => $this->warn($line),
            default => $this->error($line),
        };
    }
}

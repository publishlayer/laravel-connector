<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'publishlayer:install';

    protected $description = 'Install the PublishLayer Laravel Connector configuration';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'publishlayer-connector-config',
            '--force' => false,
        ]);

        $this->newLine();
        $this->info('Next steps:');
        $this->line('1. Set PUBLISHLAYER_API_KEY in your .env file.');
        $this->line('2. Set PUBLISHLAYER_WEBHOOK_SECRET in your .env file.');
        $this->line('3. Optionally set PUBLISHLAYER_WORKSPACE_ID.');
        $this->line('4. Configure webhook path: ' . config('publishlayer_connector.webhooks.path'));
        $this->line('5. Recommended: run webhook processing on queues for resilience.');

        return self::SUCCESS;
    }
}

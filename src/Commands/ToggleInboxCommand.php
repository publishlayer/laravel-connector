<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use PublishLayer\LaravelConnector\Services\PublishLayerInbox;

class ToggleInboxCommand extends Command
{
    protected $signature = 'pl-inbox:toggle {siteKey : The PublishLayer site key} {state : on|off}';

    protected $description = 'Toggle PublishLayer Inbox for a specific site key';

    public function handle(PublishLayerInbox $inbox): int
    {
        if (! Schema::hasTable('publishlayer_settings')) {
            $this->error('publishlayer_settings table was not found. Run migrations first.');

            return self::FAILURE;
        }

        $state = strtolower((string) $this->argument('state'));
        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('State must be one of: on, off.');

            return self::FAILURE;
        }

        $siteKey = trim((string) $this->argument('siteKey'));
        if ($siteKey === '') {
            $this->error('siteKey is required.');

            return self::FAILURE;
        }

        $enabled = $state === 'on';
        $inbox->setEnabledFor($siteKey, $enabled);

        $this->info(sprintf('PublishLayer Inbox for site [%s] is now %s.', $siteKey, $enabled ? 'enabled' : 'disabled'));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PublishLayer\LaravelConnector\Models\PublishLayerDelivery;
use PublishLayer\LaravelConnector\Models\PublishLayerFailedMessage;
use PublishLayer\LaravelConnector\Models\PublishLayerWebhookEvent;
use PublishLayer\LaravelConnector\Models\PlInboxBrief;
use PublishLayer\LaravelConnector\Services\PublishLayerInbox;
use PublishLayer\LaravelConnector\Services\SchemaState;

class ProcessWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    public function __construct(
        public readonly int $webhookEventId
    ) {
    }

    public function handle(SchemaState $schemaState): void
    {
        $event = PublishLayerWebhookEvent::find($this->webhookEventId);

        if (! $event || $event->isProcessed()) {
            return;
        }

        $action = $this->resolveAction($event->event_type);
        $delivery = $this->resolveDelivery($event, $action, $schemaState);

        try {
            match ($event->event_type) {
                'draft.ready' => $this->queueDraftReadyProcessing($event, $delivery),
                'brief.created', 'brief.ready' => $this->processBriefEvent($event, $delivery, $schemaState),
                'revision.ready' => $this->acknowledgeSimpleEvent($event, $delivery, 'revision.ready acknowledged'),
                'publish.requested' => $this->acknowledgeSimpleEvent($event, $delivery, 'publish.requested acknowledged'),
                default => $this->ignoreUnhandledEvent($event, $delivery),
            };
        } catch (\Throwable $e) {
            if ($delivery !== null) {
                $delivery->markFailed($e->getMessage());
            }
            $event->markFailed($e->getMessage());
            $this->recordFailure($event, $delivery, $e, ['stage' => 'process_webhook_event'], $schemaState);

            throw $e;
        }
    }

    public function queue(): string
    {
        return (string) config('publishlayer_connector.webhooks.queue', 'default');
    }

    private function resolveAction(?string $eventType): string
    {
        return match ($eventType) {
            'draft.ready' => 'queue_draft_ready_processing',
            'brief.created', 'brief.ready' => 'process_brief_event',
            'revision.ready' => 'acknowledge_revision_ready',
            'publish.requested' => 'acknowledge_publish_requested',
            default => 'ignore_unhandled_event',
        };
    }

    private function queueDraftReadyProcessing(PublishLayerWebhookEvent $event, ?PublishLayerDelivery $delivery): void
    {
        $queue = (string) config('publishlayer_connector.webhooks.queue', 'default');

        ProcessDraftReadyJob::dispatch($event->id)->onQueue($queue);

        if ($delivery !== null) {
            $delivery->markProcessed([
                'queued_job' => ProcessDraftReadyJob::class,
                'queue' => $queue,
            ]);
        }
    }

    private function processBriefEvent(
        PublishLayerWebhookEvent $event,
        ?PublishLayerDelivery $delivery,
        SchemaState $schemaState
    ): void
    {
        $siteKey = $this->resolveSiteKey($event);
        $inbox = app(PublishLayerInbox::class);

        $event->markProcessing();
        if ($delivery !== null) {
            $delivery->markProcessing();
        }

        if (! $inbox->enabledFor($siteKey)) {
            if ($delivery !== null) {
                $delivery->markIgnored([
                    'message' => 'PublishLayer Inbox is disabled for this site.',
                ]);
            }
            $event->markProcessed();

            return;
        }

        if (! $schemaState->hasTable('publishlayer_inbox_briefs')) {
            if ($delivery !== null) {
                $delivery->markIgnored([
                    'message' => 'publishlayer_inbox_briefs table not found.',
                ]);
            }
            $event->markProcessed();

            return;
        }

        $briefId = $this->extractBriefId($event->payload);
        if ($briefId !== null) {
            PlInboxBrief::query()->updateOrCreate(
                [
                    'site_key' => $siteKey,
                    'pl_brief_id' => $briefId,
                ],
                [
                    'title' => $this->extractTitle($event->payload),
                    'status' => 'received',
                    'brief_payload' => $event->payload,
                    'received_at' => now(),
                ]
            );
        }

        if ($delivery !== null) {
            $delivery->markProcessed([
                'message' => $briefId === null ? 'Brief payload ignored; no brief ID present.' : 'Brief payload stored.',
            ]);
        }

        $event->markProcessed();
    }

    private function acknowledgeSimpleEvent(
        PublishLayerWebhookEvent $event,
        ?PublishLayerDelivery $delivery,
        string $message
    ): void {
        $event->markProcessing();
        if ($delivery !== null) {
            $delivery->markProcessing();
            $delivery->markProcessed(['message' => $message]);
        }
        $event->markProcessed();
    }

    private function ignoreUnhandledEvent(PublishLayerWebhookEvent $event, ?PublishLayerDelivery $delivery): void
    {
        $event->markProcessing();
        if ($delivery !== null) {
            $delivery->markIgnored([
                'message' => 'No connector action configured for this event type.',
            ]);
        }
        $event->markProcessed();
    }

    /**
     * @param array<string, mixed>|null $context
     */
    private function recordFailure(
        PublishLayerWebhookEvent $event,
        ?PublishLayerDelivery $delivery,
        \Throwable $e,
        ?array $context = null,
        ?SchemaState $schemaState = null,
    ): void {
        $schemaState ??= app(SchemaState::class);

        if (! $schemaState->hasTable('publishlayer_failed_messages')) {
            return;
        }

        PublishLayerFailedMessage::create([
            'webhook_event_id' => $event->id,
            'delivery_id' => $delivery?->id,
            'site_key' => $this->resolveSiteKey($event),
            'event_id' => $event->event_id,
            'error_class' => $e::class,
            'error_message' => mb_substr($e->getMessage(), 0, 65535),
            'payload' => $event->payload,
            'context' => $context,
            'failed_at' => now(),
        ]);
    }

    private function resolveDelivery(
        PublishLayerWebhookEvent $event,
        string $action,
        SchemaState $schemaState
    ): ?PublishLayerDelivery
    {
        if (! $schemaState->hasTable('publishlayer_deliveries')) {
            return null;
        }

        return PublishLayerDelivery::updateOrCreate(
            [
                'webhook_event_id' => $event->id,
                'action' => $action,
            ],
            [
                'site_key' => $this->resolveSiteKey($event),
                'status' => PublishLayerDelivery::STATUS_QUEUED,
                'payload' => [
                    'event_type' => $event->event_type,
                ],
                'attempted_at' => now(),
            ]
        );
    }

    private function resolveSiteKey(PublishLayerWebhookEvent $event): string
    {
        $siteKey = $event->site_key ?? null;
        if (! is_string($siteKey) || trim($siteKey) === '') {
            $siteKey = (string) config('publishlayer_connector.site_key', 'default');
        }

        $siteKey = trim($siteKey);

        return $siteKey !== '' ? mb_substr($siteKey, 0, 128) : 'default';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractBriefId(array $payload): ?string
    {
        $briefId = $payload['pl_brief_id']
            ?? $payload['brief_id']
            ?? data_get($payload, 'brief.id');

        if (! is_scalar($briefId)) {
            return null;
        }

        $briefId = trim((string) $briefId);

        return $briefId !== '' ? mb_substr($briefId, 0, 128) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractTitle(array $payload): ?string
    {
        $title = $payload['title']
            ?? $payload['brief_title']
            ?? data_get($payload, 'brief.title');

        if (! is_scalar($title)) {
            return null;
        }

        $title = trim((string) $title);

        return $title !== '' ? mb_substr($title, 0, 512) : null;
    }
}

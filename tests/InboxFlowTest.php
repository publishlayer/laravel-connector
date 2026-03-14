<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Jobs\ProcessDraftReadyJob;
use PublishLayer\LaravelConnector\Models\PlInboxDraft;
use PublishLayer\LaravelConnector\Models\PublishLayerSetting;
use PublishLayer\LaravelConnector\Models\PublishLayerWebhookEvent;
use PublishLayer\LaravelConnector\Services\ImageDownloadService;
use PublishLayer\LaravelConnector\Services\SchemaState;

class InboxFlowTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_webhook_ingestion_stores_inbox_draft_and_is_idempotent(): void
    {
        $payload = [
            'type' => 'draft.ready',
            'id' => 'evt_inbox_1',
            'site_key' => 'site_a',
            'pl_draft_id' => 'draft_inbox_1',
            'pl_brief_id' => 'brief_inbox_1',
            'title' => 'Inbox Draft One',
            'content_markdown' => "# Hello\n\nThis is markdown.",
            'content_html' => '<h1>Hello</h1><p>This is html.</p>',
            'excerpt' => 'Short summary',
        ];

        $response1 = $this->postWebhook($payload, 'evt_inbox_1');
        $response1->assertOk()->assertJson(['ok' => true]);

        $event = PublishLayerWebhookEvent::query()
            ->where('event_id', 'evt_inbox_1')
            ->firstOrFail();
        (new ProcessDraftReadyJob($event->id))->handle(
            app(ImageDownloadService::class),
            app(SchemaState::class)
        );

        $this->assertDatabaseHas('publishlayer_inbox_drafts', [
            'site_key' => 'site_a',
            'pl_draft_id' => 'draft_inbox_1',
            'title' => 'Inbox Draft One',
        ]);

        $response2 = $this->postWebhook($payload, 'evt_inbox_1');
        $response2->assertOk()->assertJson(['ok' => true, 'duplicate' => true]);

        $this->assertSame(1, PlInboxDraft::query()->where('site_key', 'site_a')->where('pl_draft_id', 'draft_inbox_1')->count());
    }

    public function test_site_scoped_portal_authorization_blocks_cross_site_access(): void
    {
        $draftA = PlInboxDraft::query()->create([
            'site_key' => 'site_a',
            'pl_draft_id' => 'draft_a',
            'title' => 'Site A Draft',
            'status' => PlInboxDraft::STATUS_DRAFT,
        ]);

        $draftB = PlInboxDraft::query()->create([
            'site_key' => 'site_b',
            'pl_draft_id' => 'draft_b',
            'title' => 'Site B Draft',
            'status' => PlInboxDraft::STATUS_DRAFT,
        ]);

        $this->get('/app/content?site_key=site_a')
            ->assertOk()
            ->assertSee('Site A Draft')
            ->assertDontSee('Site B Draft');

        $this->get('/app/content/' . $draftA->id . '?site_key=site_a')->assertOk();
        $this->get('/app/content/' . $draftB->id . '?site_key=site_a')->assertNotFound();
    }

    public function test_publish_flow_sets_status_and_exposes_public_page(): void
    {
        $draft = PlInboxDraft::query()->create([
            'site_key' => 'site_a',
            'pl_draft_id' => 'draft_publish_1',
            'title' => 'Publish Me',
            'body_markdown' => "## Public Title\n\nBody text",
            'status' => PlInboxDraft::STATUS_DRAFT,
        ]);

        $this->post('/app/content/' . $draft->id . '/publish?site_key=site_a')
            ->assertRedirect('/app/content/' . $draft->id . '?site_key=site_a');

        $draft->refresh();
        $this->assertSame(PlInboxDraft::STATUS_PUBLISHED, $draft->status);
        $this->assertNotNull($draft->published_at);
        $this->assertNotNull($draft->slug);

        $this->get('/content/' . $draft->slug)
            ->assertOk()
            ->assertSee('Publish Me')
            ->assertSee('Public Title');
    }

    public function test_feature_flag_off_returns_204_and_blocks_portal(): void
    {
        PublishLayerSetting::query()->create([
            'site_key' => 'site_off',
            'setting_key' => 'pl_inbox_enabled',
            'setting_value' => ['enabled' => false],
        ]);

        $payload = [
            'type' => 'draft.ready',
            'id' => 'evt_inbox_disabled',
            'site_key' => 'site_off',
            'pl_draft_id' => 'draft_disabled',
            'title' => 'Should Not Store',
        ];

        $response = $this->postWebhook($payload, 'evt_inbox_disabled');

        $response->assertNoContent();

        $this->assertDatabaseMissing('publishlayer_inbox_drafts', [
            'site_key' => 'site_off',
            'pl_draft_id' => 'draft_disabled',
        ]);

        PlInboxDraft::query()->create([
            'site_key' => 'site_off',
            'pl_draft_id' => 'manual_draft_disabled',
            'title' => 'Manual Draft',
            'status' => PlInboxDraft::STATUS_DRAFT,
        ]);

        $this->get('/app/content?site_key=site_off')->assertNotFound();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postWebhook(array $payload, string $eventId)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

        return $this->call('POST', '/publishlayer/webhook', [], [], [], [
            'HTTP_X_PUBLISHLAYER_TIMESTAMP' => $timestamp,
            'HTTP_X_PUBLISHLAYER_SIGNATURE' => $signature,
            'HTTP_X_PUBLISHLAYER_EVENT_ID' => $eventId,
            'HTTP_X_PUBLISHLAYER_SITE_KEY' => (string) ($payload['site_key'] ?? 'default'),
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }
}

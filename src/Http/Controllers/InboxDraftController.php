<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PublishLayer\LaravelConnector\Models\PlInboxDraft;
use PublishLayer\LaravelConnector\Services\InboxContentRenderer;
use PublishLayer\LaravelConnector\Services\PublishLayerInbox;
use PublishLayer\LaravelConnector\Services\SiteKeyResolver;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InboxDraftController extends Controller
{
    public function __construct(
        private readonly SiteKeyResolver $siteKeyResolver,
        private readonly PublishLayerInbox $inbox,
        private readonly InboxContentRenderer $renderer
    ) {
    }

    public function index(Request $request)
    {
        $siteKey = $this->siteKeyResolver->resolveForRequest($request);
        $this->ensureEnabled($siteKey);

        $drafts = PlInboxDraft::query()
            ->where('site_key', $siteKey)
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('publishlayer-connector::inbox.index', [
            'siteKey' => $siteKey,
            'drafts' => $drafts,
        ]);
    }

    public function show(Request $request, int $draft)
    {
        $siteKey = $this->siteKeyResolver->resolveForRequest($request);
        $this->ensureEnabled($siteKey);

        $record = $this->findDraftOrFail($draft, $siteKey);

        return view('publishlayer-connector::inbox.show', [
            'siteKey' => $siteKey,
            'draft' => $record,
            'renderedBodyHtml' => $this->renderer->toHtml($record),
        ]);
    }

    public function approve(Request $request, int $draft): RedirectResponse
    {
        $siteKey = $this->siteKeyResolver->resolveForRequest($request);
        $this->ensureEnabled($siteKey);

        $record = $this->findDraftOrFail($draft, $siteKey);
        $record->status = PlInboxDraft::STATUS_APPROVED;
        $record->save();

        return redirect()->route('publishlayer-inbox.portal.show', [
            'draft' => $record->id,
            'site_key' => $siteKey,
        ])->with('status', 'Draft approved.');
    }

    public function publish(Request $request, int $draft): RedirectResponse
    {
        $siteKey = $this->siteKeyResolver->resolveForRequest($request);
        $this->ensureEnabled($siteKey);

        $record = $this->findDraftOrFail($draft, $siteKey);

        $this->inbox->ensureDraftSlug($record);
        $record->status = PlInboxDraft::STATUS_PUBLISHED;
        $record->published_at = now();
        $record->save();

        return redirect()->route('publishlayer-inbox.portal.show', [
            'draft' => $record->id,
            'site_key' => $siteKey,
        ])->with('status', 'Draft published.');
    }

    private function ensureEnabled(string $siteKey): void
    {
        if (! $this->inbox->enabledFor($siteKey)) {
            throw new NotFoundHttpException();
        }
    }

    private function findDraftOrFail(int $draftId, string $siteKey): PlInboxDraft
    {
        $record = PlInboxDraft::query()
            ->where('id', $draftId)
            ->where('site_key', $siteKey)
            ->first();

        if (! $record) {
            throw new NotFoundHttpException();
        }

        return $record;
    }
}

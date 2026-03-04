<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Routing\Controller;
use PublishLayer\LaravelConnector\Models\PlInboxDraft;
use PublishLayer\LaravelConnector\Services\InboxContentRenderer;
use PublishLayer\LaravelConnector\Services\PublishLayerInbox;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InboxPublicController extends Controller
{
    public function __construct(
        private readonly PublishLayerInbox $inbox,
        private readonly InboxContentRenderer $renderer
    ) {
    }

    public function show(string $slug)
    {
        $draft = PlInboxDraft::query()
            ->where('slug', $slug)
            ->where('status', PlInboxDraft::STATUS_PUBLISHED)
            ->first();

        if (! $draft || ! $this->inbox->enabledFor($draft->site_key)) {
            throw new NotFoundHttpException();
        }

        return view('publishlayer-connector::inbox.public', [
            'draft' => $draft,
            'renderedBodyHtml' => $this->renderer->toHtml($draft),
        ]);
    }
}

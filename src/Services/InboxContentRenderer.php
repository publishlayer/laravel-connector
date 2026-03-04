<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Services;

use Illuminate\Support\Str;
use PublishLayer\LaravelConnector\Models\PlInboxDraft;

class InboxContentRenderer
{
    public function toHtml(PlInboxDraft $draft): string
    {
        if (is_string($draft->body_markdown) && trim($draft->body_markdown) !== '') {
            return Str::markdown($draft->body_markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }

        if (is_string($draft->body_html) && trim($draft->body_html) !== '') {
            return nl2br(e(strip_tags($draft->body_html)));
        }

        return '<p>No content available.</p>';
    }
}

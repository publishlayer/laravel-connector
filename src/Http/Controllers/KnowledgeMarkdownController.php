<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Services\MarkdownContentService;

class KnowledgeMarkdownController extends Controller
{
    public function show(string $slug, Request $request, MarkdownContentService $markdown): Response
    {
        abort_unless($markdown->markdownEnabled(), 404);

        $article = PublishLayerArticle::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return $markdown->buildResponse($article);
    }
}

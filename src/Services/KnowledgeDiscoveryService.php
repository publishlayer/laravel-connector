<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Services;

use Illuminate\Support\Facades\Cache;
use PublishLayer\LaravelConnector\Models\PublishLayerArticle;

class KnowledgeDiscoveryService
{
    public function render(bool $full = false): string
    {
        abort_unless((bool) config('publishlayer.markdown.enabled', true), 404);

        $cacheKey = 'publishlayer:discovery:' . ($full ? 'full' : 'summary');
        $ttl = max(1, (int) config('publishlayer.markdown.cache_ttl', 300));

        return Cache::remember($cacheKey, now()->addSeconds($ttl), function () use ($full): string {
            $articles = PublishLayerArticle::query()
                ->published()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit($full ? 1000 : 100)
                ->get(['title', 'slug', 'summary']);

            $lines = [
                '# ' . config('app.name', 'Knowledge Base'),
                trim((string) data_get(config('publishlayer.labels', []), 'knowledge_base_intro', 'Published documentation mirrored from PublishLayer.')),
                '',
                '## Documentation',
            ];

            foreach ($articles as $article) {
                $lines[] = '- ' . (string) $article->title . ' (' . route('publishlayer.knowledge.markdown', ['slug' => $article->slug], false) . ')';
            }

            return trim(implode("\n", $lines)) . "\n";
        });
    }
}

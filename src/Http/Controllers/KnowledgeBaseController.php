<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Models\PublishLayerCategory;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request): View
    {
        $searchQuery = $this->normalizedSearchQuery($request);
        $articlesQuery = PublishLayerArticle::query()
            ->published()
            ->with('category');

        $this->applySearch($articlesQuery, $searchQuery);

        $articles = $articlesQuery
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($this->perPage())
            ->withQueryString();

        $categories = PublishLayerCategory::query()
            ->withCount([
                'articles as published_articles_count' => static fn (Builder $query): Builder => $query->published(),
            ])
            ->orderByDesc('published_articles_count')
            ->orderBy('name')
            ->limit($this->categoryOverviewLimit())
            ->get();

        $title = $searchQuery !== ''
            ? $this->metaTitle($this->label('search_results_title', ['query' => $searchQuery]))
            : $this->metaTitle($this->label('knowledge_base'));

        return view('publishlayer::knowledge.index', [
            'articles' => $articles,
            'categories' => $categories,
            'searchQuery' => $searchQuery,
            'breadcrumbs' => $this->breadcrumbs([
                ['label' => $this->label('knowledge_base'), 'url' => route('publishlayer.knowledge.index')],
            ]),
            'labels' => $this->labels(),
            'meta' => [
                'title' => $title,
                'description' => $searchQuery !== ''
                    ? $this->label('search_results_description', ['query' => $searchQuery])
                    : $this->defaultMetaDescription(),
                'canonical' => $this->currentUrl($request),
                'robots' => $searchQuery !== '' && $this->noindexSearchResults() ? 'noindex,follow' : null,
            ],
            'structuredData' => $this->structuredDataForPage([
                ['label' => $this->label('knowledge_base'), 'url' => route('publishlayer.knowledge.index')],
            ]),
        ]);
    }

    public function category(Request $request, string $slug): View
    {
        $category = PublishLayerCategory::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $searchQuery = $this->normalizedSearchQuery($request);
        $articlesQuery = PublishLayerArticle::query()
            ->published()
            ->with('category')
            ->where('category_id', $category->id);

        $this->applySearch($articlesQuery, $searchQuery);

        $breadcrumbs = $this->breadcrumbs([
            ['label' => $this->label('knowledge_base'), 'url' => route('publishlayer.knowledge.index')],
            ['label' => $category->name, 'url' => route('publishlayer.knowledge.category', ['slug' => $category->slug])],
        ]);

        return view('publishlayer::knowledge.category', [
            'category' => $category,
            'articles' => $articlesQuery
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate($this->perPage())
                ->withQueryString(),
            'searchQuery' => $searchQuery,
            'breadcrumbs' => $breadcrumbs,
            'labels' => $this->labels(),
            'meta' => [
                'title' => $this->metaTitle($category->name),
                'description' => $category->description ?: $this->defaultMetaDescription(),
                'canonical' => $this->currentUrl($request),
                'robots' => $searchQuery !== '' && $this->noindexSearchResults() ? 'noindex,follow' : null,
            ],
            'structuredData' => $this->structuredDataForPage($breadcrumbs),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $article = PublishLayerArticle::query()
            ->published()
            ->with(['category', 'relatedArticles' => function ($query): void {
                $query->where('status', PublishLayerArticle::STATUS_PUBLISHED)
                    ->with('category')
                    ->orderBy('publishlayer_article_relations.sort_order');
            }])
            ->where('slug', $slug)
            ->firstOrFail();

        $relatedArticles = $article->relatedArticles
            ->take($this->relatedArticlesLimit())
            ->values();

        $faqItems = $this->resolveFaqItems($article);
        $breadcrumbs = $this->breadcrumbs(array_filter([
            ['label' => $this->label('knowledge_base'), 'url' => route('publishlayer.knowledge.index')],
            $article->category
                ? ['label' => $article->category->name, 'url' => route('publishlayer.knowledge.category', ['slug' => $article->category->slug])]
                : null,
            ['label' => $article->title, 'url' => route('publishlayer.knowledge.show', ['slug' => $article->slug])],
        ]));

        $canonicalUrl = route('publishlayer.knowledge.show', ['slug' => $article->slug]);
        $readingTimeMinutes = $this->readingTimeMinutes($article);
        $lastUpdatedAt = $article->source_updated_at ?: $article->updated_at;
        $structuredData = $this->structuredDataForPage(
            $breadcrumbs,
            $this->articleSchemaEnabled() ? [$this->articleSchema($article, $canonicalUrl)] : [],
            $faqItems
        );

        return view('publishlayer::knowledge.show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
            'faqItems' => $faqItems,
            'readingTimeMinutes' => $readingTimeMinutes,
            'lastUpdatedAt' => $lastUpdatedAt,
            'breadcrumbs' => $breadcrumbs,
            'labels' => $this->labels(),
            'meta' => [
                'title' => $this->metaTitle($article->seo_title ?: $article->title),
                'description' => $article->seo_description ?: $article->summary ?: $this->excerpt($article->content_html),
                'canonical' => $canonicalUrl,
                'robots' => null,
            ],
            'structuredData' => $structuredData,
        ]);
    }

    private function applySearch(Builder $query, string $searchQuery): void
    {
        if ($searchQuery === '' || ! (bool) config('publishlayer.features.search', true)) {
            return;
        }

        $query->where(function (Builder $builder) use ($searchQuery): void {
            $builder->where('title', 'like', '%'.$searchQuery.'%')
                ->orWhere('summary', 'like', '%'.$searchQuery.'%')
                ->orWhere('content_html', 'like', '%'.$searchQuery.'%');
        });
    }

    private function normalizedSearchQuery(Request $request): string
    {
        return trim((string) $request->query('q', ''));
    }

    /**
     * @param  array<int, array{label:string,url:string}>  $breadcrumbs
     * @param  array<int, array<string, mixed>>  $extraSchemas
     * @param  array<int, array{question:string,answer:string}>  $faqItems
     * @return array<int, array<string, mixed>>
     */
    private function structuredDataForPage(array $breadcrumbs, array $extraSchemas = [], array $faqItems = []): array
    {
        $schemas = [];

        if ((bool) config('publishlayer.seo.breadcrumb_schema', true) && $breadcrumbs !== []) {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => collect($breadcrumbs)->values()->map(
                    static fn (array $item, int $index): array => [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'name' => $item['label'],
                        'item' => $item['url'],
                    ]
                )->all(),
            ];
        }

        foreach ($extraSchemas as $schema) {
            $schemas[] = $schema;
        }

        if ((bool) config('publishlayer.seo.faq_schema', false) && $faqItems !== []) {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => collect($faqItems)->map(
                    static fn (array $item): array => [
                        '@type' => 'Question',
                        'name' => $item['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $item['answer'],
                        ],
                    ]
                )->all(),
            ];
        }

        return $schemas;
    }

    /**
     * @return array<int, array{label:string,url:string}>
     */
    private function breadcrumbs(array $items): array
    {
        if (! (bool) config('publishlayer.features.breadcrumbs', true)) {
            return [];
        }

        return array_values($items);
    }

    /**
     * @return array<string, string>
     */
    private function labels(): array
    {
        return (array) config('publishlayer.labels', []);
    }

    private function label(string $key, array $replace = []): string
    {
        $value = (string) data_get(config('publishlayer.labels', []), $key, Str::headline(str_replace('_', ' ', $key)));

        foreach ($replace as $replaceKey => $replaceValue) {
            $value = str_replace(':'.$replaceKey, (string) $replaceValue, $value);
        }

        return $value;
    }

    private function metaTitle(string $title): string
    {
        $suffix = trim((string) config('publishlayer.seo.meta_title_suffix', ''));

        return $suffix !== '' ? $title.' '.$suffix : $title;
    }

    private function defaultMetaDescription(): string
    {
        $configured = trim((string) config('publishlayer.seo.default_meta_description', ''));

        return $configured !== ''
            ? $configured
            : $this->label('knowledge_base_intro');
    }

    private function currentUrl(Request $request): string
    {
        return $request->fullUrl();
    }

    private function perPage(): int
    {
        return max(1, (int) config('publishlayer.pagination.per_page', 12));
    }

    private function categoryOverviewLimit(): int
    {
        return max(1, (int) config('publishlayer.knowledge.category_overview_limit', 6));
    }

    private function relatedArticlesLimit(): int
    {
        return max(1, (int) config('publishlayer.knowledge.related_articles_limit', 4));
    }

    private function noindexSearchResults(): bool
    {
        return (bool) config('publishlayer.seo.noindex_search_results', true);
    }

    private function articleSchemaEnabled(): bool
    {
        return (bool) config('publishlayer.seo.article_schema', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function articleSchema(PublishLayerArticle $article, string $canonicalUrl): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => (string) config('publishlayer.seo.article_schema_type', 'Article'),
            'headline' => $article->title,
            'description' => $article->seo_description ?: $article->summary ?: $this->excerpt($article->content_html),
            'datePublished' => $article->published_at?->toIso8601String(),
            'dateModified' => ($article->source_updated_at ?: $article->updated_at)?->toIso8601String(),
            'mainEntityOfPage' => $canonicalUrl,
            'articleSection' => $article->category?->name,
            'inLanguage' => app()->getLocale(),
            'image' => $article->featured_image_url ?: null,
        ];
    }

    /**
     * @return array<int, array{question:string,answer:string}>
     */
    private function resolveFaqItems(PublishLayerArticle $article): array
    {
        if (! (bool) config('publishlayer.seo.faq_schema', false)) {
            return [];
        }

        $resolver = config('publishlayer.seo.faq_schema_resolver');
        if ($resolver === null) {
            return [];
        }

        if (is_string($resolver) && class_exists($resolver)) {
            $resolver = app($resolver);
        }

        if (is_callable($resolver)) {
            $items = app()->call($resolver, ['article' => $article]);
        } elseif (is_object($resolver) && method_exists($resolver, 'resolve')) {
            $items = app()->call([$resolver, 'resolve'], ['article' => $article]);
        } else {
            return [];
        }

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $question = trim((string) ($item['question'] ?? ''));
                $answer = trim((string) ($item['answer'] ?? ''));

                if ($question === '' || $answer === '') {
                    return null;
                }

                return [
                    'question' => $question,
                    'answer' => $answer,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function readingTimeMinutes(PublishLayerArticle $article): int
    {
        $wordCount = str_word_count(strip_tags($article->content_html));
        $wordsPerMinute = max(50, (int) config('publishlayer.reading_time.words_per_minute', 220));

        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }

    private function excerpt(string $contentHtml, int $limit = 160): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($contentHtml)) ?? ''), $limit, '');
    }
}

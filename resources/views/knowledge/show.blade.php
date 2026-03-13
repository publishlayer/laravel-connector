@extends(config('publishlayer.layout', 'layouts.app'))

@section('title', $meta['title'])

@push('head')
    @include('publishlayer::knowledge.partials.head', ['meta' => $meta, 'structuredData' => $structuredData])
@endpush

@section('content')
    <article class="publishlayer-knowledge-article">
        @includeWhen(!empty($breadcrumbs), 'publishlayer::knowledge.partials.breadcrumbs', ['breadcrumbs' => $breadcrumbs])

        <header>
            <p><a href="{{ route('publishlayer.knowledge.index') }}">{{ $labels['back_to_knowledge_base'] ?? 'Back to Knowledge Base' }}</a></p>
            <h1>{{ $article->title }}</h1>

            @if ($article->category)
                <p>
                    {{ $labels['category'] ?? 'Category' }}:
                    <a href="{{ route('publishlayer.knowledge.category', ['slug' => $article->category->slug]) }}">{{ $article->category->name }}</a>
                </p>
            @endif

            <p>
                @if ($article->published_at)
                    <span>{{ $labels['published'] ?? 'Published' }} {{ $article->published_at->toFormattedDateString() }}</span>
                @endif
                @if (config('publishlayer.features.show_last_updated', true) && $lastUpdatedAt)
                    <span> · {{ $labels['last_updated'] ?? 'Last updated' }} {{ $lastUpdatedAt->toFormattedDateString() }}</span>
                @endif
                @if (config('publishlayer.features.show_reading_time', true))
                    <span> · {{ str_replace(':minutes', (string) $readingTimeMinutes, $labels['reading_time'] ?? ':minutes min read') }}</span>
                @endif
            </p>

            @if ($article->summary)
                <p>{{ $article->summary }}</p>
            @endif
        </header>

        <section>
            {!! $article->content_html !!}
        </section>

        @if ($relatedArticles->isNotEmpty())
            <aside aria-label="{{ $labels['related_articles'] ?? 'Related articles' }}">
                <h2>{{ $labels['related_articles'] ?? 'Related articles' }}</h2>
                <div>
                    @foreach ($relatedArticles as $relatedArticle)
                        <article>
                            <h3>
                                <a href="{{ route('publishlayer.knowledge.show', ['slug' => $relatedArticle->slug]) }}">{{ $relatedArticle->title }}</a>
                            </h3>
                            @if ($relatedArticle->summary)
                                <p>{{ $relatedArticle->summary }}</p>
                            @endif
                            <p>
                                @if ($relatedArticle->category)
                                    <span>{{ $relatedArticle->category->name }}</span>
                                @endif
                                @if ($relatedArticle->published_at)
                                    <span> · {{ $labels['updated'] ?? 'Updated' }} {{ $relatedArticle->published_at->toFormattedDateString() }}</span>
                                @endif
                            </p>
                        </article>
                    @endforeach
                </div>
            </aside>
        @endif
    </article>
@endsection

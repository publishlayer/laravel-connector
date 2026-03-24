<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('PUBLISHLAYER_ENABLED', true),

    'mode' => env('PUBLISHLAYER_MODE', 'hosted_views'),

    'api_key' => env('PUBLISHLAYER_API_KEY', ''),

    'site_id' => env('PUBLISHLAYER_SITE_ID', ''),

    'auth_header' => env('PUBLISHLAYER_AUTH_HEADER', 'X-PublishLayer-Api-Key'),

    'sync_signing_secret' => env('PUBLISHLAYER_SYNC_SIGNING_SECRET', ''),

    'signature_header' => env('PUBLISHLAYER_SIGNATURE_HEADER', 'X-PublishLayer-Signature'),

    'signature_timestamp_header' => env('PUBLISHLAYER_TIMESTAMP_HEADER', 'X-PublishLayer-Timestamp'),

    'signature_tolerance_seconds' => (int) env('PUBLISHLAYER_SIGNATURE_TOLERANCE_SECONDS', 300),

    'route_prefix' => env('PUBLISHLAYER_ROUTE_PREFIX', 'knowledge'),

    'api_prefix' => env('PUBLISHLAYER_API_PREFIX', 'api/publishlayer'),

    'layout' => env('PUBLISHLAYER_LAYOUT', 'layouts.app'),

    'web_middleware' => ['web'],

    'api_middleware' => ['api'],

    'markdown' => [
        'enabled' => (bool) env('PUBLISHLAYER_MARKDOWN_ENABLED', true),
        'accept_negotiation' => (bool) env('PUBLISHLAYER_MARKDOWN_ACCEPT_NEGOTIATION', true),
        'cache_ttl' => (int) env('PUBLISHLAYER_MARKDOWN_CACHE_TTL', 300),
    ],

    'pagination' => [
        'per_page' => (int) env('PUBLISHLAYER_PER_PAGE', 12),
    ],

    'features' => [
        'search' => (bool) env('PUBLISHLAYER_SEARCH_ENABLED', true),
        'breadcrumbs' => (bool) env('PUBLISHLAYER_BREADCRUMBS_ENABLED', true),
        'category_overview' => (bool) env('PUBLISHLAYER_CATEGORY_OVERVIEW_ENABLED', true),
        'show_last_updated' => (bool) env('PUBLISHLAYER_SHOW_LAST_UPDATED', true),
        'show_reading_time' => (bool) env('PUBLISHLAYER_SHOW_READING_TIME', true),
    ],

    'knowledge' => [
        'category_overview_limit' => (int) env('PUBLISHLAYER_CATEGORY_OVERVIEW_LIMIT', 6),
        'related_articles_limit' => (int) env('PUBLISHLAYER_RELATED_ARTICLES_LIMIT', 4),
    ],

    'reading_time' => [
        'words_per_minute' => (int) env('PUBLISHLAYER_READING_TIME_WPM', 220),
    ],

    'seo' => [
        'enabled' => (bool) env('PUBLISHLAYER_SEO_ENABLED', true),
        'canonical' => (bool) env('PUBLISHLAYER_CANONICAL_ENABLED', true),
        'article_schema' => (bool) env('PUBLISHLAYER_ARTICLE_SCHEMA_ENABLED', true),
        'breadcrumb_schema' => (bool) env('PUBLISHLAYER_BREADCRUMB_SCHEMA_ENABLED', true),
        'faq_schema' => (bool) env('PUBLISHLAYER_FAQ_SCHEMA_ENABLED', false),
        'faq_schema_resolver' => null,
        'article_schema_type' => env('PUBLISHLAYER_ARTICLE_SCHEMA_TYPE', 'Article'),
        'default_meta_description' => env('PUBLISHLAYER_META_DESCRIPTION', 'Browse synced PublishLayer knowledge articles rendered locally by your Laravel site.'),
        'meta_title_suffix' => env('PUBLISHLAYER_META_TITLE_SUFFIX', ''),
        'noindex_search_results' => (bool) env('PUBLISHLAYER_NOINDEX_SEARCH_RESULTS', true),
    ],

    'labels' => [
        'knowledge_base' => 'Knowledge Base',
        'knowledge_base_intro' => 'Browse synced PublishLayer knowledge articles rendered locally by your Laravel site.',
        'categories' => 'Categories',
        'category_overview' => 'Browse by category',
        'articles' => 'Articles',
        'category' => 'Category',
        'search_label' => 'Search articles',
        'search_placeholder' => 'Search the knowledge base',
        'search_button' => 'Search',
        'search_clear' => 'Clear search',
        'search_results_title' => 'Search results for ":query"',
        'search_results_description' => 'Search results in the knowledge base for ":query".',
        'no_articles' => 'No published knowledge articles are available yet.',
        'no_category_articles' => 'No published articles are available in this category.',
        'back_to_knowledge_base' => 'Back to Knowledge Base',
        'related_articles' => 'Related articles',
        'last_updated' => 'Last updated',
        'reading_time' => ':minutes min read',
        'updated' => 'Updated',
        'published' => 'Published',
        'view_all' => 'View all',
        'pagination_previous' => 'Previous',
        'pagination_next' => 'Next',
        'pagination_summary' => 'Page :current of :last',
        'results_summary' => ':count article|:count articles',
    ],

    'auto_publish' => (bool) env('PUBLISHLAYER_AUTO_PUBLISH', false),

    // Disable category persistence and hosted category pages when your integration does not need them.
    'enable_categories' => (bool) env('PUBLISHLAYER_ENABLE_CATEGORIES', true),

    // Disable related article references when you want to keep synced payloads minimal.
    'enable_related_articles' => (bool) env('PUBLISHLAYER_ENABLE_RELATED_ARTICLES', true),
];

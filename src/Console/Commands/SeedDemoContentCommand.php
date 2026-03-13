<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Models\PublishLayerCategory;

class SeedDemoContentCommand extends Command
{
    protected $signature = 'publishlayer:seed-demo-content {--fresh : Remove previously seeded demo content before seeding}';

    protected $description = 'Seed demo knowledge base content for the PublishLayer connector';

    public function handle(): int
    {
        foreach (['publishlayer_categories', 'publishlayer_articles'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error(sprintf('Required table missing: %s. Run php artisan migrate first.', $table));

                return self::FAILURE;
            }
        }

        if ($this->option('fresh')) {
            PublishLayerArticle::query()
                ->where('source_publishlayer_id', 'like', 'demo_%')
                ->delete();

            PublishLayerCategory::query()
                ->where('source_publishlayer_id', 'like', 'demo_%')
                ->delete();
        }

        $category = PublishLayerCategory::query()->updateOrCreate([
            'source_publishlayer_id' => 'demo_category_getting_started',
        ], [
            'name' => 'Getting Started',
            'slug' => 'getting-started',
            'description' => 'Demo content created by the PublishLayer connector install flow.',
        ]);

        PublishLayerArticle::query()->updateOrCreate([
            'source_publishlayer_id' => 'demo_article_welcome',
        ], [
            'title' => 'Welcome to your PublishLayer Knowledge Base',
            'slug' => 'welcome-to-your-publishlayer-knowledge-base',
            'summary' => 'A sample article that confirms local rendering is working.',
            'content_html' => '<p>This demo article confirms that your connector routes, database, and Blade rendering are working.</p><p>Replace it by syncing real content from PublishLayer.</p>',
            'seo_title' => 'PublishLayer Knowledge Base Demo',
            'seo_description' => 'Demo knowledge base article created by the PublishLayer connector.',
            'featured_image_url' => null,
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'category_id' => $category->id,
            'published_at' => now(),
            'source_updated_at' => now(),
        ]);

        PublishLayerArticle::query()->updateOrCreate([
            'source_publishlayer_id' => 'demo_article_headless',
        ], [
            'title' => 'Connector modes: hosted views and headless',
            'slug' => 'connector-modes-hosted-views-and-headless',
            'summary' => 'Explains the two connector modes.',
            'content_html' => '<p>Hosted views mode renders knowledge pages locally with your app layout.</p><p>Headless mode keeps sync and health endpoints active while disabling the public knowledge base routes.</p>',
            'seo_title' => 'PublishLayer Connector Modes',
            'seo_description' => 'Demo article for hosted views and headless connector modes.',
            'featured_image_url' => null,
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'category_id' => $category->id,
            'published_at' => now(),
            'source_updated_at' => now(),
        ]);

        $this->info('Demo PublishLayer knowledge content seeded.');
        $this->line('Open: /'.trim((string) config('publishlayer.route_prefix', 'knowledge'), '/'));

        return self::SUCCESS;
    }
}

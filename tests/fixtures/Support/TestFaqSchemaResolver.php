<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests\Fixtures\Support;

use PublishLayer\LaravelConnector\Models\PublishLayerArticle;

class TestFaqSchemaResolver
{
    /**
     * @return array<int, array{question:string,answer:string}>
     */
    public function __invoke(PublishLayerArticle $article): array
    {
        return [
            [
                'question' => 'What is '.$article->title.'?',
                'answer' => 'It is a synced knowledge article used for connector testing.',
            ],
        ];
    }
}

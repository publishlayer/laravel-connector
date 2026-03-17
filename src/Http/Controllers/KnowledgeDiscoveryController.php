<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use PublishLayer\LaravelConnector\Services\KnowledgeDiscoveryService;

class KnowledgeDiscoveryController extends Controller
{
    public function llms(KnowledgeDiscoveryService $discovery): Response
    {
        return response($discovery->render(false), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function llmsFull(KnowledgeDiscoveryService $discovery): Response
    {
        return response($discovery->render(true), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}

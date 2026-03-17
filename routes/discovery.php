<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PublishLayer\LaravelConnector\Http\Controllers\KnowledgeDiscoveryController;

Route::get('/llms.txt', [KnowledgeDiscoveryController::class, 'llms'])
    ->name('discovery.llms');
Route::get('/llms-full.txt', [KnowledgeDiscoveryController::class, 'llmsFull'])
    ->name('discovery.llms-full');

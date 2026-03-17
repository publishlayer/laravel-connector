<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PublishLayer\LaravelConnector\Http\Controllers\KnowledgeBaseController;
use PublishLayer\LaravelConnector\Http\Controllers\KnowledgeMarkdownController;

Route::get('/', [KnowledgeBaseController::class, 'index'])->name('knowledge.index');
Route::get('/categorie/{slug}', [KnowledgeBaseController::class, 'category'])->name('knowledge.category');
Route::get('/{slug}.md', [KnowledgeMarkdownController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('knowledge.markdown');
Route::get('/{slug}', [KnowledgeBaseController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('knowledge.show');

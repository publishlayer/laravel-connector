<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PublishLayer\LaravelConnector\Http\Controllers\KnowledgeBaseController;

Route::get('/', [KnowledgeBaseController::class, 'index'])->name('knowledge.index');
Route::get('/categorie/{slug}', [KnowledgeBaseController::class, 'category'])->name('knowledge.category');
Route::get('/{slug}', [KnowledgeBaseController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('knowledge.show');

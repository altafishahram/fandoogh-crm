<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MarketplaceConversationController;
use App\Http\Middleware\EnsureMarketplacePrincipal;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureMarketplacePrincipal::class, 'throttle:api'])->prefix('marketplace')->name('marketplace.')->group(function (): void {
    Route::get('/conversations', [MarketplaceConversationController::class, 'index'])->name('conversations.index');
    Route::post('/listings/{listing}/conversations', [MarketplaceConversationController::class, 'store'])->whereNumber('listing')->name('conversations.store');
    Route::get('/conversations/{conversation}/messages', [MarketplaceConversationController::class, 'messages'])->whereNumber('conversation')->name('conversations.messages');
    Route::post('/conversations/{conversation}/messages', [MarketplaceConversationController::class, 'send'])->whereNumber('conversation')->middleware('throttle:30,1')->name('conversations.send');
    Route::get('/conversations/{conversation}/messages/{message}/image', [MarketplaceConversationController::class, 'image'])->whereNumber(['conversation', 'message'])->name('conversations.image');
    Route::post('/conversations/{conversation}/read', [MarketplaceConversationController::class, 'read'])->whereNumber('conversation')->name('conversations.read');
});

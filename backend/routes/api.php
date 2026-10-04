<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AgencyLocationController;
use App\Http\Controllers\Api\V1\AgentPermissionController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerHistoryController;
use App\Http\Controllers\Api\V1\CustomerNoteController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\MatchNotificationController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\OwnerController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfilePasswordController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\PropertyHistoryController;
use App\Http\Controllers\Api\V1\PropertyImageController;
use App\Http\Controllers\Api\V1\PropertyNoteController;
use App\Http\Controllers\Api\V1\PublicationController;
use App\Http\Controllers\Api\V1\PublicAuthController;
use App\Http\Controllers\Api\V1\RelatedMatchController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SavedFilterController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/locations/provinces', [LocationController::class, 'provinces']);
    Route::get('/locations/counties', [LocationController::class, 'counties']);
    Route::get('/locations/cities', [LocationController::class, 'cities']);
    Route::get('/public/auth/config', [PublicAuthController::class, 'config']);
    Route::post('/public/auth/login', [PublicAuthController::class, 'login'])->middleware('throttle:30,1');
    Route::middleware(['auth:sanctum', 'abilities:marketplace:public'])->group(function (): void {
        Route::get('/public/auth/me', [PublicAuthController::class, 'me']);
        Route::post('/public/auth/logout', [PublicAuthController::class, 'logout']);
    });
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/marketplace/listings', [MarketplaceController::class, 'index'])->name('marketplace.listings.index');
        Route::get('/marketplace/listings/{listing}', [MarketplaceController::class, 'show'])->whereNumber('listing')->name('marketplace.listings.show');
        Route::get('/marketplace/listings/{listing}/images/{image}/content', [MarketplaceController::class, 'image'])->whereNumber(['listing', 'image'])->name('marketplace.images.content');
    });
    if (file_exists(__DIR__.'/marketplace_chat.php')) {
        require __DIR__.'/marketplace_chat.php';
    }
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/login', [MobileAuthController::class, 'login'])->name('login');
    });

    Route::middleware(['auth:sanctum', 'abilities:mobile', 'throttle:api', 'tenant'])
        ->group(function (): void {
            Route::get('/auth/me', [MobileAuthController::class, 'me'])->name('auth.me');
            Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');
            Route::post('/auth/logout-all', [MobileAuthController::class, 'logoutAll'])->name('auth.logout-all');
            Route::put('/profile/password', [ProfilePasswordController::class, 'update'])
                ->name('profile.password.update');

            Route::middleware('password.changed')->group(function (): void {
                Route::put('/agency/location', [AgencyLocationController::class, 'update']);
                Route::get('/properties/{property}/publication', [PublicationController::class, 'show']);
                Route::put('/properties/{property}/publication', [PublicationController::class, 'update']);
                Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
                Route::apiResource('owners', OwnerController::class)->only(['index', 'store', 'show', 'update']);
                Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
                Route::post('/properties', [PropertyController::class, 'store'])
                    ->middleware('idempotent:property')->name('properties.store');
                Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
                Route::put('/properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
                Route::patch('/properties/{property}', [PropertyController::class, 'update']);
                Route::post('/properties/{property}/status', [PropertyController::class, 'changeStatus'])
                    ->middleware('idempotent:property-status')->name('properties.status');
                Route::get('/properties/{property}/history', [PropertyHistoryController::class, 'index'])
                    ->name('properties.history');
                Route::get('/properties/{property}/matches', [RelatedMatchController::class, 'property'])
                    ->name('properties.matches');
                Route::apiResource('properties.notes', PropertyNoteController::class)
                    ->only(['index', 'store', 'update', 'destroy'])->shallow(false);
                Route::apiResource('properties.images', PropertyImageController::class)
                    ->only(['index', 'store', 'update', 'destroy'])->shallow(false);
                Route::get('/properties/{property}/images/{image}/content', [PropertyImageController::class, 'content'])
                    ->name('properties.images.content');

                Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
                Route::post('/customers', [CustomerController::class, 'store'])
                    ->middleware('idempotent:customer')->name('customers.store');
                Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
                Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
                Route::patch('/customers/{customer}', [CustomerController::class, 'update']);
                Route::get('/customers/{customer}/history', [CustomerHistoryController::class, 'index'])
                    ->name('customers.history');
                Route::get('/customers/{customer}/matches', [RelatedMatchController::class, 'customer'])
                    ->name('customers.matches');
                Route::apiResource('customers.notes', CustomerNoteController::class)
                    ->only(['index', 'store', 'update', 'destroy'])->shallow(false);
                Route::apiResource('saved-filters', SavedFilterController::class)
                    ->only(['index', 'store', 'update', 'destroy']);
                Route::get('/dashboard', DashboardController::class)->name('dashboard');
                Route::get('/reports/me', ReportController::class)->name('reports.me');
                Route::get('/search', SearchController::class)->name('search');
                Route::get('/sync', SyncController::class)->name('sync');
                Route::get('/agents', [AgentPermissionController::class, 'index'])->name('agents.index');
                Route::put('/agents/{agent}/permissions', [AgentPermissionController::class, 'update'])
                    ->name('agents.permissions.update');

                Route::get('/match-notifications', [MatchNotificationController::class, 'index'])
                    ->name('match-notifications.index');
                Route::get('/match-notifications/unread-count', [MatchNotificationController::class, 'unreadCount'])
                    ->name('match-notifications.unread-count');
                Route::get('/match-notifications/{matchNotification}', [MatchNotificationController::class, 'show'])
                    ->whereNumber('matchNotification')->name('match-notifications.show');
                Route::put('/match-notifications/{matchNotification}/read', [MatchNotificationController::class, 'markRead'])
                    ->whereNumber('matchNotification')->name('match-notifications.read');
                Route::post('/match-notifications/{matchNotification}/read', [MatchNotificationController::class, 'markRead'])
                    ->whereNumber('matchNotification')->name('match-notifications.read.store');
            });
        });
});

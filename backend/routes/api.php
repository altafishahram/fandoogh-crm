<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AgentPermissionController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerHistoryController;
use App\Http\Controllers\Api\V1\CustomerNoteController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\OwnerController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfilePasswordController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\PropertyHistoryController;
use App\Http\Controllers\Api\V1\PropertyImageController;
use App\Http\Controllers\Api\V1\PropertyNoteController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SavedFilterController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
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
            });
        });
});

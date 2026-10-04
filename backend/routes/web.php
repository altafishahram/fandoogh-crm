<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'phase' => 'آماده بهره‌برداری آزمایشی',
    'status' => 'فعال',
]))->name('home');

require __DIR__.'/marketplace_web.php';

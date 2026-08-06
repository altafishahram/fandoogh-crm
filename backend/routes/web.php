<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'ملک بان',
    'phase' => 'آماده بهره‌برداری آزمایشی',
    'status' => 'فعال',
]))->name('home');

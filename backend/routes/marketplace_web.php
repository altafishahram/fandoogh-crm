<?php

declare(strict_types=1);

use App\Application\Marketplace\Services\ListingAccessService;
use App\Http\Controllers\Api\V1\MarketplaceConversationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware(['auth', 'throttle:api'])->prefix('marketplace/media')->name('marketplace.web.')->group(function (): void {
    Route::get('/listings/{listing}/images/{image}', function (Request $request, int $listing, int $image) {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $publication = app(ListingAccessService::class)->findVisible($listing, $actor, 'agency');
        $record = $publication->images()->findOrFail($image);
        abort_unless($record->width * $record->height <= 25_000_000, 413);
        abort_unless(Storage::disk('local')->exists($record->storage_path), 404);
        // Return pixels only, never source EXIF or filenames.
        $original = Storage::disk('local')->get($record->storage_path);
        abort_unless(is_string($original), 404);
        $source = @imagecreatefromstring($original);
        abort_unless($source !== false, 404);
        ob_start();
        imagejpeg($source, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($source);

        return response($bytes, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    })->whereNumber(['listing', 'image'])->name('listing-image');
    Route::get('/conversations/{conversation}/messages/{message}/image', [MarketplaceConversationController::class, 'image'])->whereNumber(['conversation', 'message'])->name('chat-image');
});

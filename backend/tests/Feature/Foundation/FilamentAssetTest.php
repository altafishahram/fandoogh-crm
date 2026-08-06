<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Tests\TestCase;

final class FilamentAssetTest extends TestCase
{
    public function test_required_filament_runtime_assets_are_published(): void
    {
        self::assertFileExists(public_path('js/filament/filament/app.js'));
        self::assertFileExists(public_path('js/filament/schemas/schemas.js'));
        self::assertFileExists(public_path('css/filament/filament/app.css'));
        self::assertFileExists(public_path('fonts/filament/filament/inter/index.css'));

        self::assertGreaterThan(0, filesize(public_path('js/filament/filament/app.js')) ?: 0);
        self::assertGreaterThan(0, filesize(public_path('css/filament/filament/app.css')) ?: 0);
    }
}

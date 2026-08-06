<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;

final class PanelBranding
{
    public static function apply(Panel $panel, string $section): Panel
    {
        return $panel
            ->brandName("ملک بان · {$section}")
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Slate,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                static fn () => view('filament.shared.brand-theme'),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                static fn () => view('filament.shared.brand-credit'),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                static fn () => view('filament.shared.brand-credit', ['compact' => true]),
            );
    }
}

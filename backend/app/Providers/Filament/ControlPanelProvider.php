<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Control\Pages\Dashboard;
use App\Filament\Shared\Pages\Profile;
use App\Filament\Shared\Support\PanelBranding;
use App\Http\Middleware\EnsureWebPasswordChanged;
use App\Http\Middleware\EstablishTenantContext;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class ControlPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return PanelBranding::apply(
            $panel->default()->id('control')->path('control')->login()->profile(Profile::class),
            'مدیریت کل',
        )
            ->discoverResources(in: app_path('Filament/Control/Resources'), for: 'App\\Filament\\Control\\Resources')
            ->discoverPages(in: app_path('Filament/Control/Pages'), for: 'App\\Filament\\Control\\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Control/Widgets'), for: 'App\\Filament\\Control\\Widgets')
            ->middleware($this->middleware())
            ->authMiddleware([Authenticate::class, EstablishTenantContext::class, EnsureWebPasswordChanged::class]);
    }

    /** @return list<class-string> */
    private function middleware(): array
    {
        return [
            EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
            AuthenticateSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
            SubstituteBindings::class, DisableBladeIconComponents::class, DispatchServingFilamentEvent::class,
        ];
    }
}

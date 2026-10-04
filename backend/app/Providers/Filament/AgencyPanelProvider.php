<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Agency\Pages\Dashboard;
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

final class AgencyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return PanelBranding::apply(
            $panel->id('agency')->path('agency')->login()->profile(Profile::class),
            'مدیریت آژانس',
        )
            ->discoverResources(in: app_path('Filament/Agency/Resources'), for: 'App\\Filament\\Agency\\Resources')
            ->discoverPages(in: app_path('Filament/Agency/Pages'), for: 'App\\Filament\\Agency\\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Agency/Widgets'), for: 'App\\Filament\\Agency\\Widgets')
            ->middleware($this->middleware())
            ->authMiddleware([Authenticate::class, EstablishTenantContext::class, EnsureWebPasswordChanged::class], isPersistent: true);
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

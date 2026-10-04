<?php

declare(strict_types=1);

namespace App\Filament\Control\Widgets;

use App\Application\Dashboard\Services\DashboardService;
use App\Filament\Control\Resources\Agencies\AgencyResource;
use App\Filament\Control\Resources\Users\UserResource;
use App\Filament\Control\Support\PlatformPanelAccess;
use Filament\Widgets\Widget;

final class PlatformDetails extends Widget
{
    protected string $view = 'filament.control.platform-details';

    protected int|string|array $columnSpan = 'full';

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, string> */
    public array $urls = [];

    public function mount(): void
    {
        $this->refreshPlatform();
    }

    public function refreshPlatform(): void
    {
        $actor = PlatformPanelAccess::actor();
        $this->data = array_intersect_key(app(DashboardService::class)->for($actor), array_flip([
            'agencies', 'users', 'agency_totals',
        ]));
        $this->urls = [
            'agencies' => AgencyResource::getUrl('index', panel: 'control'),
            'agency_create' => AgencyResource::getUrl('create', panel: 'control'),
            'managers' => UserResource::getUrl('index', panel: 'control'),
            'manager_create' => UserResource::getUrl('create', panel: 'control'),
        ];
    }
}

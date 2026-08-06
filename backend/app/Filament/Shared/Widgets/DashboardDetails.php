<?php

declare(strict_types=1);

namespace App\Filament\Shared\Widgets;

use App\Application\Dashboard\Services\DashboardService;
use App\Models\User;
use Filament\Widgets\Widget;

abstract class DashboardDetails extends Widget
{
    protected string $view = 'filament.shared.dashboard-details';

    protected int|string|array $columnSpan = 'full';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        $this->data = app(DashboardService::class)->for($user);
    }
}

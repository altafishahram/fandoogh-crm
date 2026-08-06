<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AgencyPanelProvider;
use App\Providers\Filament\AgentPanelProvider;
use App\Providers\Filament\ControlPanelProvider;

return [
    AppServiceProvider::class,
    ControlPanelProvider::class,
    AgencyPanelProvider::class,
    AgentPanelProvider::class,
];

<?php

declare(strict_types=1);

namespace App\Filament\Shared\Widgets;

use App\Application\Dashboard\Services\DashboardService;
use App\Filament\Agency\Resources\Customers\CustomerResource as AgencyCustomerResource;
use App\Filament\Agency\Resources\Properties\PropertyResource as AgencyPropertyResource;
use App\Filament\Agent\Resources\Customers\CustomerResource as AgentCustomerResource;
use App\Filament\Agent\Resources\Properties\PropertyResource as AgentPropertyResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

abstract class DashboardDetails extends Widget
{
    protected string $view = 'filament.shared.dashboard-details';

    protected int|string|array $columnSpan = 'full';

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, string> */
    public array $urls = [];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        $this->data = app(DashboardService::class)->for($user);
        $agentPanel = Filament::getCurrentPanel()?->getId() === 'agent';
        $propertyResource = $agentPanel ? AgentPropertyResource::class : AgencyPropertyResource::class;
        $customerResource = $agentPanel ? AgentCustomerResource::class : AgencyCustomerResource::class;
        $this->urls = [
            'properties' => $propertyResource::getUrl('index'),
            'property_create' => $propertyResource::getUrl('create'),
            'customers' => $customerResource::getUrl('index'),
            'customer_create' => $customerResource::getUrl('create'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Services;

use App\Domain\Tenancy\AgencyScope;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\Property;
use App\Models\PropertyHistory;
use App\Models\PropertyNote;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class DashboardService
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $role = $user->roleName();
        $cacheKey = implode(':', [
            'dashboard', $role->value, (string) $user->getKey(), (string) ($user->agency_id ?? 'platform'),
        ]);

        return Cache::remember($cacheKey, 60, fn (): array => match ($role) {
            RoleName::SuperAdmin => $this->platform(),
            RoleName::AgencyManager => $this->agency((int) $user->agency_id),
            RoleName::Agent => $this->agent($user),
        });
    }

    /** @return array<string, mixed> */
    private function platform(): array
    {
        $properties = Property::withoutGlobalScope(AgencyScope::class)
            ->selectRaw('agency_id, COUNT(*) AS aggregate')->groupBy('agency_id')->pluck('aggregate', 'agency_id');
        $customers = Customer::withoutGlobalScope(AgencyScope::class)
            ->selectRaw('agency_id, COUNT(*) AS aggregate')->groupBy('agency_id')->pluck('aggregate', 'agency_id');

        return [
            'agencies' => [
                'active' => Agency::query()->where('is_active', true)->count(),
                'suspended' => Agency::query()->where('is_active', false)->count(),
            ],
            'users' => [
                'managers' => User::role(RoleName::AgencyManager->value)->where('is_active', true)->count(),
                'agents' => User::role(RoleName::Agent->value)->where('is_active', true)->count(),
            ],
            'agency_totals' => Agency::query()->orderBy('name')->get(['id', 'name'])->map(
                static fn (Agency $agency): array => [
                    'agency_id' => $agency->getKey(), 'name' => $agency->name,
                    'properties' => (int) ($properties[$agency->getKey()] ?? 0),
                    'customers' => (int) ($customers[$agency->getKey()] ?? 0),
                ],
            )->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function agency(int $agencyId): array
    {
        return [
            'properties_by_status' => Property::query()->selectRaw('status, COUNT(*) AS aggregate')
                ->groupBy('status')->pluck('aggregate', 'status')->map(fn ($count): int => (int) $count)->all(),
            'customers_by_status_intent' => Customer::query()->selectRaw('status, intent, COUNT(*) AS aggregate')
                ->groupBy('status', 'intent')->get()->map(fn (Customer $row): array => [
                    'status' => $row->getRawOriginal('status'), 'intent' => $row->getRawOriginal('intent'),
                    'count' => (int) $row->getAttribute('aggregate'),
                ])->all(),
            'unassigned_properties' => Property::query()->whereNull('assigned_agent_id')->count(),
            'agent_workload' => $this->agentWorkload($agencyId),
            'recent_history' => PropertyHistory::query()->latest('occurred_at')->limit(10)
                ->get(['id', 'property_id', 'changed_by_user_id', 'action', 'occurred_at'])->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    private function agent(User $user): array
    {
        return [
            'properties_by_status' => Property::query()->where('assigned_agent_id', $user->getKey())
                ->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')
                ->pluck('aggregate', 'status')->map(fn ($count): int => (int) $count)->all(),
            'active_customers' => Customer::query()->where('assigned_agent_id', $user->getKey())
                ->where('status', 'active')->count(),
            'recent_properties' => Property::query()->where('assigned_agent_id', $user->getKey())
                ->latest('updated_at')->limit(10)->get(['id', 'code', 'title', 'status', 'updated_at'])->toArray(),
            'recent_notes' => PropertyNote::query()->whereHas(
                'property', fn ($query) => $query->where('assigned_agent_id', $user->getKey()),
            )
                ->latest()->limit(10)->get(['id', 'property_id', 'body', 'created_at'])->toArray(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function agentWorkload(int $agencyId): array
    {
        $agents = User::role(RoleName::Agent->value)
            ->where('agency_id', $agencyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $propertyCounts = Property::query()
            ->whereNotNull('assigned_agent_id')
            ->whereIn('status', ['available', 'reserved'])
            ->selectRaw('assigned_agent_id, COUNT(*) AS aggregate')
            ->groupBy('assigned_agent_id')
            ->pluck('aggregate', 'assigned_agent_id');
        $customerCounts = Customer::query()
            ->whereNotNull('assigned_agent_id')
            ->where('status', 'active')
            ->selectRaw('assigned_agent_id, COUNT(*) AS aggregate')
            ->groupBy('assigned_agent_id')
            ->pluck('aggregate', 'assigned_agent_id');

        return array_values($agents
            ->map(static fn (User $agent): array => [
                'agent_id' => $agent->getKey(), 'name' => $agent->name,
                'active_properties' => (int) ($propertyCounts[$agent->getKey()] ?? 0),
                'active_customers' => (int) ($customerCounts[$agent->getKey()] ?? 0),
            ])->all());
    }
}

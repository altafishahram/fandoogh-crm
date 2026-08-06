<?php

declare(strict_types=1);

namespace App\Application\Report\Services;

use App\Application\Report\Data\ReportPeriod;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\User\Enums\RoleName;
use App\Models\Customer;
use App\Models\Property;
use App\Models\PropertyHistory;
use App\Models\User;

final class ReportService
{
    /** @return array<string, mixed> */
    public function for(User $user, ReportPeriod $period): array
    {
        return match ($user->roleName()) {
            RoleName::AgencyManager => $this->agency((int) $user->agency_id, $period),
            RoleName::Agent => $this->agent($user, $period),
            RoleName::SuperAdmin => throw new DomainConflictException('کاربر مدیریت کل گزارش عملیاتی ندارد.'),
        };
    }

    /** @return array<string, mixed> */
    private function agency(int $agencyId, ReportPeriod $period): array
    {
        $agentAggregates = $this->agentAggregates($agencyId);

        return [
            'period' => $this->period($period),
            'properties_by_status' => $this->propertyGroup('status'),
            'properties_by_type' => $this->propertyGroup('property_type'),
            'properties_by_transaction' => $this->propertyGroup('transaction_type'),
            'properties_by_agent' => $agentAggregates['properties_by_agent'],
            'new_properties' => Property::query()->whereBetween('created_at', [$period->fromUtc, $period->toUtc])->count(),
            'closed_properties' => Property::query()->whereBetween('closed_at', [$period->fromUtc, $period->toUtc])->count(),
            'customers_by_status' => $this->customerGroup('status'),
            'customers_by_intent' => $this->customerGroup('intent'),
            'customers_by_agent' => $agentAggregates['customers_by_agent'],
            'agent_workload' => $agentAggregates['agent_workload'],
        ];
    }

    /** @return array<string, mixed> */
    private function agent(User $user, ReportPeriod $period): array
    {
        return [
            'period' => $this->period($period),
            'assigned_properties' => Property::query()->where('assigned_agent_id', $user->getKey())->count(),
            'property_status_changes' => PropertyHistory::query()
                ->where('changed_by_user_id', $user->getKey())->where('action', 'status_changed')
                ->whereBetween('occurred_at', [$period->fromUtc, $period->toUtc])
                ->selectRaw('to_status, COUNT(*) AS aggregate')->groupBy('to_status')
                ->pluck('aggregate', 'to_status')->map(fn ($count): int => (int) $count)->all(),
            'customers_created' => Customer::query()->where('created_by_user_id', $user->getKey())
                ->whereBetween('created_at', [$period->fromUtc, $period->toUtc])->count(),
            'customers_by_status' => Customer::query()->where('assigned_agent_id', $user->getKey())
                ->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')
                ->pluck('aggregate', 'status')->map(fn ($count): int => (int) $count)->all(),
        ];
    }

    /** @return array<string, int> */
    private function propertyGroup(string $field): array
    {
        $query = match ($field) {
            'status' => Property::query()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status'),
            'property_type' => Property::query()->selectRaw('property_type, COUNT(*) AS aggregate')->groupBy('property_type'),
            'transaction_type' => Property::query()->selectRaw('transaction_type, COUNT(*) AS aggregate')->groupBy('transaction_type'),
            default => throw new DomainConflictException('گروه‌بندی گزارش املاک پشتیبانی نمی‌شود.'),
        };

        return $query->pluck('aggregate', $field)->map(fn ($count): int => (int) $count)->all();
    }

    /** @return array<string, int> */
    private function customerGroup(string $field): array
    {
        $query = match ($field) {
            'status' => Customer::query()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status'),
            'intent' => Customer::query()->selectRaw('intent, COUNT(*) AS aggregate')->groupBy('intent'),
            default => throw new DomainConflictException('گروه‌بندی گزارش مشتریان پشتیبانی نمی‌شود.'),
        };

        return $query->pluck('aggregate', $field)->map(fn ($count): int => (int) $count)->all();
    }

    /**
     * @return array{
     *     properties_by_agent: list<array{agent_id: mixed, name: string, count: int}>,
     *     customers_by_agent: list<array{agent_id: mixed, name: string, count: int}>,
     *     agent_workload: list<array{agent_id: mixed, name: string, properties: int, customers: int}>
     * }
     */
    private function agentAggregates(int $agencyId): array
    {
        $agents = User::role(RoleName::Agent->value)
            ->where('agency_id', $agencyId)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
        $propertyCounts = Property::query()
            ->whereNotNull('assigned_agent_id')
            ->selectRaw('assigned_agent_id, COUNT(*) AS aggregate')
            ->groupBy('assigned_agent_id')
            ->pluck('aggregate', 'assigned_agent_id');
        $customerCounts = Customer::query()
            ->whereNotNull('assigned_agent_id')
            ->selectRaw('assigned_agent_id, COUNT(*) AS aggregate')
            ->groupBy('assigned_agent_id')
            ->pluck('aggregate', 'assigned_agent_id');

        $propertiesByAgent = array_values($agents->map(
            static fn (User $agent): array => [
                'agent_id' => $agent->getKey(), 'name' => $agent->name,
                'count' => (int) ($propertyCounts[$agent->getKey()] ?? 0),
            ],
        )->all());
        $customersByAgent = array_values($agents->map(
            static fn (User $agent): array => [
                'agent_id' => $agent->getKey(), 'name' => $agent->name,
                'count' => (int) ($customerCounts[$agent->getKey()] ?? 0),
            ],
        )->all());
        $agentWorkload = array_values($agents->where('is_active', true)->map(
            static fn (User $agent): array => [
                'agent_id' => $agent->getKey(), 'name' => $agent->name,
                'properties' => (int) ($propertyCounts[$agent->getKey()] ?? 0),
                'customers' => (int) ($customerCounts[$agent->getKey()] ?? 0),
            ],
        )->all());

        return [
            'properties_by_agent' => $propertiesByAgent,
            'customers_by_agent' => $customersByAgent,
            'agent_workload' => $agentWorkload,
        ];
    }

    /** @return array{from: string, to: string} */
    private function period(ReportPeriod $period): array
    {
        return ['from' => (string) $period->fromUtc->toISOString(), 'to' => (string) $period->toUtc->toISOString()];
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Search\Services;

use App\Domain\SavedFilter\Exceptions\InvalidFilterException;
use App\Domain\User\Enums\RoleName;
use App\Models\Customer;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;

final class GlobalSearchService
{
    /** @return array<string, list<array<string, mixed>>> */
    public function search(User $user, string $term): array
    {
        $term = mb_substr(trim($term), 0, 100);
        if (mb_strlen($term) < 2) {
            throw new InvalidFilterException('عبارت جست‌وجو باید دست‌کم دو نویسه داشته باشد.');
        }
        $escaped = addcslashes($term, '%_\\');
        $agent = $user->roleName() === RoleName::Agent;

        $properties = array_values(Property::query()
            ->when($agent, fn ($query) => $query->where('assigned_agent_id', $user->getKey()))
            ->where(fn ($query) => $query->where('code', 'like', $escaped.'%')
                ->orWhere('title', 'like', '%'.$escaped.'%')->orWhere('city', 'like', '%'.$escaped.'%')
                ->orWhere('district', 'like', '%'.$escaped.'%')->orWhere('street_address', 'like', '%'.$escaped.'%'))
            ->limit(20)->get(['id', 'code', 'title', 'status', 'city'])
            ->map(fn (Property $property): array => $property->toArray())->all());

        $owners = array_values(Owner::query()
            ->when($agent, fn ($query) => $query->whereHas('properties',
                fn ($properties) => $properties->where('assigned_agent_id', $user->getKey())))
            ->where(fn ($query) => $query->where('first_name', 'like', '%'.$escaped.'%')
                ->orWhere('last_name', 'like', '%'.$escaped.'%')->orWhere('company_name', 'like', '%'.$escaped.'%')
                ->orWhere('mobile', 'like', $escaped.'%')->orWhere('email', 'like', $escaped.'%'))
            ->limit(20)->get(['id', 'owner_type', 'first_name', 'last_name', 'company_name', 'mobile', 'email'])
            ->map(fn (Owner $owner): array => $owner->toArray())->all());

        $customers = array_values(Customer::query()
            ->when($agent, fn ($query) => $query->where('assigned_agent_id', $user->getKey()))
            ->where(fn ($query) => $query->where('first_name', 'like', '%'.$escaped.'%')
                ->orWhere('last_name', 'like', '%'.$escaped.'%')->orWhere('mobile', 'like', $escaped.'%')
                ->orWhere('email', 'like', $escaped.'%'))
            ->limit(20)->get(['id', 'first_name', 'last_name', 'mobile', 'email', 'status', 'intent'])
            ->map(fn (Customer $customer): array => $customer->toArray())->all());

        return compact('properties', 'owners', 'customers');
    }
}

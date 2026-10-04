<?php

declare(strict_types=1);

namespace App\Application\Search\Services;

use App\Application\Matching\Services\RelatedMatchQuery;
use App\Domain\SavedFilter\Exceptions\InvalidFilterException;
use App\Models\Customer;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;

final class GlobalSearchService
{
    public function __construct(private readonly RelatedMatchQuery $matches) {}

    /** @return array<string, list<array<string, mixed>>> */
    public function search(User $user, string $term): array
    {
        $term = mb_substr(trim($term), 0, 100);
        if (mb_strlen($term) < 2) {
            throw new InvalidFilterException('عبارت جست‌وجو باید دست‌کم دو نویسه داشته باشد.');
        }
        $escaped = addcslashes($term, '%_\\');
        $properties = array_values($this->matches->withSummaries(Property::query()
            ->select(['id', 'code', 'title', 'status', 'city'])
            ->where(fn ($query) => $query->where('code', 'like', $escaped.'%')
                ->orWhere('title', 'like', '%'.$escaped.'%')->orWhere('city', 'like', '%'.$escaped.'%')
                ->orWhere('district', 'like', '%'.$escaped.'%')->orWhere('street_address', 'like', '%'.$escaped.'%')
                ->orWhereHas('owners', fn ($owners) => $owners->where('full_name', 'like', '%'.$escaped.'%')))
            ->limit(20), $user, 'property')->get()
            ->map(fn (Property $property): array => array_merge($property->toArray(), [
                'match_summary' => RelatedMatchQuery::summaryOf($property),
            ]))->all());

        $owners = array_values(Owner::query()
            ->where(fn ($query) => $query->where('first_name', 'like', '%'.$escaped.'%')
                ->orWhere('last_name', 'like', '%'.$escaped.'%')->orWhere('company_name', 'like', '%'.$escaped.'%')
                ->orWhere('mobile', 'like', $escaped.'%')->orWhere('email', 'like', $escaped.'%'))
            ->limit(20)->get(['id', 'owner_type', 'first_name', 'last_name', 'company_name', 'mobile', 'email'])
            ->map(fn (Owner $owner): array => $owner->toArray())->all());

        $customers = array_values($this->matches->withSummaries(Customer::query()
            ->select(['id', 'first_name', 'last_name', 'mobile', 'email', 'status', 'intent'])
            ->where(fn ($query) => $query->where('full_name', 'like', '%'.$escaped.'%')
                ->orWhere('first_name', 'like', '%'.$escaped.'%')
                ->orWhere('last_name', 'like', '%'.$escaped.'%')->orWhere('mobile', 'like', $escaped.'%')
                ->orWhere('email', 'like', $escaped.'%'))
            ->limit(20), $user, 'customer')->get()
            ->map(fn (Customer $customer): array => array_merge($customer->toArray(), [
                'match_summary' => RelatedMatchQuery::summaryOf($customer),
            ]))->all());

        return compact('properties', 'owners', 'customers');
    }
}

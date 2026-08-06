<?php

declare(strict_types=1);

namespace App\Application\SavedFilter\Services;

use App\Domain\SavedFilter\Enums\FilterModule;

final class FilterCatalog
{
    /** @return list<string> */
    public function fields(FilterModule $module): array
    {
        return match ($module) {
            FilterModule::Properties => [
                'status', 'property_type', 'transaction_type', 'city', 'district', 'assigned_agent_id',
                'min_sale_price', 'max_sale_price', 'min_monthly_rent', 'max_monthly_rent',
                'min_deposit', 'max_deposit', 'min_area', 'max_area', 'min_bedrooms', 'has_images',
                'created_from', 'created_to', 'updated_from', 'updated_to',
            ],
            FilterModule::Owners => ['owner_type', 'city', 'query'],
            FilterModule::Customers => [
                'status', 'intent', 'assigned_agent_id', 'preferred_property_type',
                'min_budget', 'max_budget', 'city', 'district', 'created_from', 'created_to',
                'updated_from', 'updated_to',
            ],
        };
    }

    /** @return list<string> */
    public function sorts(FilterModule $module): array
    {
        return match ($module) {
            FilterModule::Properties => [
                'created_at', 'updated_at', 'code', 'title', 'sale_price',
                'monthly_rent', 'area_sqm', 'status',
            ],
            FilterModule::Owners => ['created_at', 'updated_at', 'first_name', 'last_name', 'company_name'],
            FilterModule::Customers => ['created_at', 'updated_at', 'last_name', 'budget_max', 'status'],
        };
    }
}

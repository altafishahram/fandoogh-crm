<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Application\Matching\Services\MatchingRebuildDispatcher;
use App\Application\Property\Contracts\PropertyRepositoryContract;
use App\Application\Property\Data\UpdatePropertyData;
use App\Application\Shared\Services\AgentAssignmentValidator;
use App\Application\Shared\Services\OptimisticLock;
use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\RoleName;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePropertyService
{
    public function __construct(
        private PropertyRepositoryContract $properties,
        private PropertyInvariantValidator $validator,
        private AgentAssignmentValidator $agents,
        private OptimisticLock $optimisticLock,
        private PropertyHistoryWriter $history,
        private PropertyPricingCalculator $pricing,
        private TenantContext $tenant,
        private MatchingRebuildDispatcher $matching,
    ) {}

    public function execute(User $actor, Property $property, UpdatePropertyData $data): Property
    {
        $updated = DB::transaction(function () use ($actor, $property, $data): Property {
            $locked = $this->properties->lock((int) $property->getKey());
            if ($data->expectedVersion !== null) {
                $this->optimisticLock->assertVersion($locked, $data->expectedVersion);
            } else {
                $this->optimisticLock->assertCurrent($locked, $data->expectedUpdatedAt);
            }

            if ($locked->status->isReadOnly()) {
                throw new DomainConflictException('این ملک در وضعیت فعلی فقط‌خواندنی است.');
            }

            $attributes = $data->attributes;
            $transactionChanged = array_key_exists('transaction_type', $attributes)
                && $attributes['transaction_type'] !== $locked->transaction_type;
            $assignmentChanged = array_key_exists('assigned_agent_id', $attributes)
                && $attributes['assigned_agent_id'] !== $locked->assigned_agent_id;
            if ($actor->roleName() === RoleName::Agent && ($transactionChanged || $assignmentChanged)) {
                throw new DomainConflictException('کارشناس اجازه تغییر نوع معامله یا مسئول ملک را ندارد.');
            }

            if (array_key_exists('transaction_type', $attributes)
                && $locked->status !== PropertyStatus::Available) {
                throw new DomainConflictException('نوع معامله فقط در وضعیت موجود قابل تغییر است.');
            }

            if (array_key_exists('assigned_agent_id', $attributes) && $attributes['assigned_agent_id'] !== null) {
                $this->agents->validate((int) $attributes['assigned_agent_id'], $this->tenant->agencyId());
            }

            $merged = array_merge($locked->getAttributes(), $attributes);
            if ($data->priceInputMode === 'per_sqm' && $data->salePricePerSqm !== null) {
                $area = (string) ($merged['area_sqm'] ?? '0');
                $attributes['sale_price'] = $this->pricing->totalFromPerSquareMeter($data->salePricePerSqm, $area);
                $merged['sale_price'] = $attributes['sale_price'];
            }
            $this->validator->commercial($merged);
            $before = $locked->getAttributes();
            $locked->fill($attributes);
            $locked->matching_eligible_at = now();
            $locked->lock_version = (int) $locked->lock_version + 1;
            $locked = $this->properties->save($locked);

            if ($data->embeddedOwner !== null) {
                $owner = $locked->owners()->firstOrFail();
                $owner->fill($data->embeddedOwner->attributes());
                $owner->save();
            }
            $changes = $this->historyChanges($before, $locked);

            if ($changes !== []) {
                $action = array_key_exists('assigned_agent_id', $changes)
                    ? PropertyHistoryAction::AssignmentChanged
                    : PropertyHistoryAction::Updated;
                $this->history->write($locked, $actor, $action, changedFields: $changes);
            }

            return $locked;
        });

        $this->matching->dispatchForAgency((int) $updated->agency_id);

        return $updated;
    }

    /** @param array<string, mixed> $before
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function historyChanges(array $before, Property $property): array
    {
        $allowed = [
            'title', 'property_type', 'transaction_type', 'assigned_agent_id', 'sale_price',
            'deposit_amount', 'monthly_rent', 'area_sqm', 'bedrooms', 'bathrooms', 'floor_number',
            'total_floors', 'year_built', 'parking_spaces', 'has_storage_room', 'has_elevator',
            'has_balcony', 'city', 'district', 'postal_code', 'latitude', 'longitude', 'available_from',
            'plaque', 'units_per_floor', 'master_bedrooms', 'toilet_types', 'cabinet_type',
            'heating_systems', 'cooling_systems', 'flooring_type', 'renovation_status',
            'has_master_bathroom', 'heating_type', 'cooling_type', 'building_orientation', 'deed_type',
            'has_loan', 'is_exchangeable', 'has_pool', 'has_jacuzzi', 'has_sauna',
            'building_type', 'structure_type', 'has_water', 'has_electricity', 'has_gas',
            'telephone_line_count', 'land_area', 'building_area', 'can_aggregate', 'land_frontage',
            'delivery_status', 'evacuation_date', 'is_convertible', 'minimum_deposit',
        ];
        $changes = [];

        foreach ($allowed as $field) {
            $new = $property->getRawOriginal($field);
            if (($before[$field] ?? null) !== $new) {
                $changes[$field] = ['old' => $before[$field] ?? null, 'new' => $new];
            }
        }

        return $changes;
    }
}

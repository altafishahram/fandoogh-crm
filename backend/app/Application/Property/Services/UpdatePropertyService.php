<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

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
        private TenantContext $tenant,
    ) {}

    public function execute(User $actor, Property $property, UpdatePropertyData $data): Property
    {
        return DB::transaction(function () use ($actor, $property, $data): Property {
            $locked = $this->properties->lock((int) $property->getKey());
            $this->optimisticLock->assertCurrent($locked, $data->expectedUpdatedAt);

            if ($locked->status->isReadOnly()) {
                throw new DomainConflictException('این ملک در وضعیت فعلی فقط‌خواندنی است.');
            }

            $attributes = $data->attributes;
            if ($actor->roleName() === RoleName::Agent
                && (array_key_exists('transaction_type', $attributes)
                    || array_key_exists('assigned_agent_id', $attributes))) {
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
            $this->validator->commercial($merged);
            $before = $locked->getAttributes();
            $locked->fill($attributes);
            $locked = $this->properties->save($locked);
            $changes = $this->historyChanges($before, $locked);

            if ($changes !== []) {
                $action = array_key_exists('assigned_agent_id', $changes)
                    ? PropertyHistoryAction::AssignmentChanged
                    : PropertyHistoryAction::Updated;
                $this->history->write($locked, $actor, $action, changedFields: $changes);
            }

            return $locked;
        });
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

<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Application\Geography\Services\LocationValidator;
use App\Application\Matching\Services\MatchingRebuildDispatcher;
use App\Application\Property\Contracts\PropertyRepositoryContract;
use App\Application\Property\Data\CreatePropertyData;
use App\Application\Property\Data\PropertyOwnershipData;
use App\Application\Shared\Services\AgentAssignmentValidator;
use App\Domain\Owner\Enums\OwnerType;
use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\RoleName;
use App\Models\AgencySettings;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreatePropertyService
{
    public function __construct(
        private PropertyRepositoryContract $properties,
        private PropertyInvariantValidator $validator,
        private AgentAssignmentValidator $agents,
        private PropertyHistoryWriter $history,
        private PropertyPricingCalculator $pricing,
        private TenantContext $tenant,
        private MatchingRebuildDispatcher $matching,
    ) {}

    public function execute(User $actor, CreatePropertyData $data): Property
    {
        if ($data->embeddedOwner === null) {
            $this->validator->ownerships($data->owners);
        }
        $attributes = app(LocationValidator::class)->normalize($data->attributes());
        if ($data->transactionType->value === 'sale' && $data->priceInputMode === 'per_sqm') {
            if ($data->salePricePerSqm === null || $data->areaSqm === null) {
                throw new DomainConflictException('مبلغ هر متر و مساحت برای محاسبه مبلغ کل الزامی است.');
            }
            $attributes['sale_price'] = $this->pricing->totalFromPerSquareMeter($data->salePricePerSqm, $data->areaSqm);
        }
        $this->validator->commercial($attributes);

        $assignedAgentId = $actor->roleName() === RoleName::Agent
            ? (int) $actor->getKey()
            : $data->assignedAgentId;

        if ($assignedAgentId !== null) {
            $this->agents->validate($assignedAgentId, $this->tenant->agencyId());
        }

        $property = DB::connection()->transaction(function () use ($actor, $data, $assignedAgentId, $attributes): Property {
            $settings = AgencySettings::query()->lockForUpdate()->firstOrFail();
            $sequence = $settings->next_property_sequence;
            $code = $settings->property_code_prefix.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            $settings->next_property_sequence = $sequence + 1;
            $settings->save();

            $property = $this->properties->create($attributes + [
                'code' => $code,
                'status' => $data->status ?? PropertyStatus::Available,
                'assigned_agent_id' => $assignedAgentId,
                'created_by_user_id' => $actor->getKey(),
                'created_by_role' => $actor->roleName()->value,
                'currency_code' => $this->tenant->agency()->currency_code,
                'currency_unit' => 'toman',
                'lock_version' => 1,
                'matching_eligible_at' => now(),
            ]);

            if ($data->embeddedOwner !== null) {
                $owner = Owner::query()->create($data->embeddedOwner->attributes() + [
                    'owner_type' => OwnerType::Person,
                    'first_name' => $data->embeddedOwner->fullName,
                    'last_name' => '',
                    'created_by_user_id' => $actor->getKey(),
                ]);
                $this->attachOwner($property, new PropertyOwnershipData((int) $owner->getKey(), '100.00', true));
            } else {
                foreach ($data->owners as $ownership) {
                    $this->attachOwner($property, $ownership);
                }
            }

            $this->history->write($property, $actor, PropertyHistoryAction::Created);

            return $property->load(['owners', 'assignedAgent']);
        }, 3);

        $this->matching->dispatchForAgency((int) $property->agency_id);

        return $property;
    }

    private function attachOwner(Property $property, PropertyOwnershipData $ownership): void
    {
        $property->owners()->attach($ownership->ownerId, [
            'agency_id' => $this->tenant->agencyId(),
            'ownership_percentage' => $ownership->ownershipPercentage,
            'is_primary' => $ownership->isPrimary,
        ]);
    }
}

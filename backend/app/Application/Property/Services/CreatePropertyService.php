<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Application\Property\Contracts\PropertyRepositoryContract;
use App\Application\Property\Data\CreatePropertyData;
use App\Application\Property\Data\PropertyOwnershipData;
use App\Application\Shared\Services\AgentAssignmentValidator;
use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\RoleName;
use App\Models\AgencySettings;
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
        private TenantContext $tenant,
    ) {}

    public function execute(User $actor, CreatePropertyData $data): Property
    {
        $this->validator->ownerships($data->owners);
        $this->validator->commercial($data->attributes());

        $assignedAgentId = $actor->roleName() === RoleName::Agent
            ? (int) $actor->getKey()
            : $data->assignedAgentId;

        if ($assignedAgentId !== null) {
            $this->agents->validate($assignedAgentId, $this->tenant->agencyId());
        }

        return DB::connection()->transaction(function () use ($actor, $data, $assignedAgentId): Property {
            $settings = AgencySettings::query()->lockForUpdate()->firstOrFail();
            $sequence = $settings->next_property_sequence;
            $code = $settings->property_code_prefix.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            $settings->next_property_sequence = $sequence + 1;
            $settings->save();

            $property = $this->properties->create($data->attributes() + [
                'code' => $code,
                'status' => PropertyStatus::Available,
                'assigned_agent_id' => $assignedAgentId,
                'created_by_user_id' => $actor->getKey(),
                'currency_code' => $this->tenant->agency()->currency_code,
            ]);

            foreach ($data->owners as $ownership) {
                $this->attachOwner($property, $ownership);
            }

            $this->history->write($property, $actor, PropertyHistoryAction::Created);

            return $property->load(['owners', 'assignedAgent']);
        }, 3);
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

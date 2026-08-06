<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Application\Shared\Services\AgentAssignmentValidator;
use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\Tenancy\TenantContext;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class RestorePropertyService
{
    public function __construct(
        private AgentAssignmentValidator $agents,
        private PropertyHistoryWriter $history,
        private TenantContext $tenant,
    ) {}

    public function execute(User $actor, Property $property): Property
    {
        return DB::transaction(function () use ($actor, $property): Property {
            $ownerIds = DB::table('property_owner')
                ->where('agency_id', $this->tenant->agencyId())
                ->where('property_id', $property->getKey())
                ->pluck('owner_id');
            if ($ownerIds->isEmpty()
                || Owner::query()->whereKey($ownerIds)->count() !== $ownerIds->count()) {
                throw new DomainConflictException('پیش از بازیابی ملک، همه مالکان مرتبط باید فعال باشند.');
            }
            if ($property->assigned_agent_id !== null) {
                $this->agents->validate((int) $property->assigned_agent_id, $this->tenant->agencyId());
            }

            $property->restore();
            $this->history->write($property, $actor, PropertyHistoryAction::Restored);

            return $property->refresh();
        });
    }
}

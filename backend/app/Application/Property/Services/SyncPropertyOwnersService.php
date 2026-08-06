<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Application\Property\Contracts\PropertyRepositoryContract;
use App\Application\Property\Data\PropertyOwnershipData;
use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\Tenancy\TenantContext;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class SyncPropertyOwnersService
{
    public function __construct(
        private PropertyRepositoryContract $properties,
        private PropertyInvariantValidator $validator,
        private PropertyHistoryWriter $history,
        private TenantContext $tenant,
    ) {}

    /** @param list<PropertyOwnershipData> $ownerships */
    public function execute(User $actor, Property $property, array $ownerships): Property
    {
        $this->validator->ownerships($ownerships);

        return DB::transaction(function () use ($actor, $property, $ownerships): Property {
            $locked = $this->properties->lock((int) $property->getKey());
            if ($locked->status->isReadOnly()) {
                throw new DomainConflictException('در وضعیت فقط‌خواندنی ملک، تغییر مالکان مجاز نیست.');
            }

            $sync = [];
            foreach ($ownerships as $ownership) {
                $sync[$ownership->ownerId] = [
                    'agency_id' => $this->tenant->agencyId(),
                    'ownership_percentage' => $ownership->ownershipPercentage,
                    'is_primary' => $ownership->isPrimary,
                ];
            }
            $locked->owners()->sync($sync);
            $this->history->write($locked, $actor, PropertyHistoryAction::OwnersChanged);

            return $locked->load('owners');
        });
    }
}

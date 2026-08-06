<?php

declare(strict_types=1);

namespace App\Application\Owner\Services;

use App\Application\Owner\Contracts\OwnerRepositoryContract;
use App\Application\Owner\Data\OwnerData;
use App\Application\Shared\Services\OptimisticLock;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\User\Enums\RoleName;
use App\Models\Owner;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class UpdateOwnerService
{
    public function __construct(
        private OwnerRepositoryContract $owners,
        private OwnerInvariantValidator $validator,
        private OptimisticLock $optimisticLock,
    ) {}

    public function execute(User $actor, Owner $owner, OwnerData $data, CarbonImmutable $expectedUpdatedAt): Owner
    {
        $this->optimisticLock->assertCurrent($owner, $expectedUpdatedAt);
        $this->validator->validate($data);
        $attributes = $data->attributes();

        if ($actor->roleName() === RoleName::Agent) {
            foreach (['owner_type', 'first_name', 'last_name', 'company_name', 'identity_number_encrypted'] as $field) {
                if ($owner->getAttribute($field) !== $attributes[$field]) {
                    throw new DomainConflictException('کارشناس اجازه تغییر اطلاعات هویتی مالک را ندارد.');
                }
            }
        }

        $owner->fill($attributes);

        return $this->owners->save($owner);
    }
}

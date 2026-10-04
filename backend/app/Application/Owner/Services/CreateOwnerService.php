<?php

declare(strict_types=1);

namespace App\Application\Owner\Services;

use App\Application\Geography\Services\LocationValidator;
use App\Application\Owner\Contracts\OwnerRepositoryContract;
use App\Application\Owner\Data\OwnerData;
use App\Models\Owner;
use App\Models\User;

final readonly class CreateOwnerService
{
    public function __construct(
        private OwnerRepositoryContract $owners,
        private OwnerInvariantValidator $validator,
    ) {}

    public function execute(User $actor, OwnerData $data): Owner
    {
        $this->validator->validate($data);

        return $this->owners->create(app(LocationValidator::class)->normalize($data->attributes()) + [
            'created_by_user_id' => $actor->getKey(),
        ]);
    }
}

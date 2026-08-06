<?php

declare(strict_types=1);

namespace App\Application\Owner\Services;

use App\Application\Owner\Data\OwnerData;
use App\Domain\Owner\Enums\OwnerType;
use App\Domain\Shared\Exceptions\DomainConflictException;

final class OwnerInvariantValidator
{
    public function validate(OwnerData $data): void
    {
        if ($data->mobile === null && $data->phone === null && $data->email === null) {
            throw new DomainConflictException('ثبت دست‌کم یک راه تماس برای مالک الزامی است.');
        }

        if ($data->ownerType === OwnerType::Person
            && ($data->firstName === null || $data->lastName === null)) {
            throw new DomainConflictException('برای مالک حقیقی، نام و نام خانوادگی الزامی است.');
        }

        if ($data->ownerType === OwnerType::Company && $data->companyName === null) {
            throw new DomainConflictException('برای مالک حقوقی، نام شرکت الزامی است.');
        }
    }
}

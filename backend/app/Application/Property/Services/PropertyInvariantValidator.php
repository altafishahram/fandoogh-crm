<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Application\Property\Data\PropertyOwnershipData;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Models\Owner;

final class PropertyInvariantValidator
{
    /** @param list<PropertyOwnershipData> $ownerships */
    public function ownerships(array $ownerships): void
    {
        if ($ownerships === []) {
            throw new DomainConflictException('هر ملک باید دست‌کم یک مالک داشته باشد.');
        }

        $ownerIds = array_map(static fn (PropertyOwnershipData $item): int => $item->ownerId, $ownerships);

        if (count($ownerIds) !== count(array_unique($ownerIds))) {
            throw new DomainConflictException('هر مالک فقط یک‌بار می‌تواند به ملک متصل شود.');
        }

        if (count(array_filter($ownerships, static fn (PropertyOwnershipData $item): bool => $item->isPrimary)) !== 1) {
            throw new DomainConflictException('دقیقاً یک مالک باید به‌عنوان مالک اصلی مشخص شود.');
        }

        $knownShares = array_filter(
            $ownerships,
            static fn (PropertyOwnershipData $item): bool => $item->ownershipPercentage !== null,
        );

        if ($knownShares !== [] && count($knownShares) !== count($ownerships)) {
            throw new DomainConflictException('درصد مالکیت باید برای همه مالکان وارد شود یا برای هیچ‌کدام وارد نشود.');
        }

        if ($knownShares !== []) {
            $sumCents = array_sum(array_map(
                static fn (PropertyOwnershipData $item): int => (int) round(((float) $item->ownershipPercentage) * 100),
                $ownerships,
            ));

            if ($sumCents !== 10_000) {
                throw new DomainConflictException('مجموع درصد مالکیت باید دقیقاً ۱۰۰ باشد.');
            }
        }

        if (Owner::query()->whereKey($ownerIds)->count() !== count($ownerIds)) {
            throw new DomainConflictException('همه مالکان باید متعلق به آژانس جاری باشند.');
        }
    }

    /** @param array<string, mixed> $attributes */
    public function commercial(array $attributes): void
    {
        $transaction = $attributes['transaction_type'];
        $transaction = $transaction instanceof TransactionType ? $transaction : TransactionType::from((string) $transaction);
        $salePrice = $attributes['sale_price'] ?? null;
        $deposit = $attributes['deposit_amount'] ?? null;
        $rent = $attributes['monthly_rent'] ?? null;

        if ($transaction === TransactionType::Sale) {
            if (! $this->positive($salePrice) || $deposit !== null || $rent !== null) {
                throw new DomainConflictException('ملک فروشی به قیمت فروش مثبت نیاز دارد و نباید مبلغ اجاره داشته باشد.');
            }
        } elseif ($salePrice !== null || ! $this->positive($rent) || ! $this->nonNegative($deposit)) {
            throw new DomainConflictException('ملک اجاره‌ای به اجاره ماهانه مثبت و ودیعه نامنفی نیاز دارد.');
        }

        $year = $attributes['year_built'] ?? null;
        if ($year !== null && ((int) $year < 1800 || (int) $year > ((int) date('Y')) + 1)) {
            throw new DomainConflictException('سال ساخت خارج از بازه مجاز است.');
        }

        $latitude = $attributes['latitude'] ?? null;
        $longitude = $attributes['longitude'] ?? null;
        if (($latitude === null) !== ($longitude === null)) {
            throw new DomainConflictException('عرض و طول جغرافیایی باید باهم وارد شوند.');
        }
    }

    private function positive(mixed $value): bool
    {
        return $value !== null && (float) $value > 0;
    }

    private function nonNegative(mixed $value): bool
    {
        return $value !== null && (float) $value >= 0;
    }
}

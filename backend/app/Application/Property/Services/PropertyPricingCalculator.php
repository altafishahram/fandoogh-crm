<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Domain\Shared\Exceptions\DomainConflictException;

final class PropertyPricingCalculator
{
    private const DEPOSIT_STEP = '1000000';

    private const RENT_PER_STEP = '30000';

    public function totalFromPerSquareMeter(string $perSquareMeter, string $area): string
    {
        $perSquareMeter = $this->numeric($perSquareMeter);
        $area = $this->numeric($area);

        return $this->roundHalfUp(bcmul($perSquareMeter, $area, 6));
    }

    public function perSquareMeter(string $total, string $area): string
    {
        $total = $this->numeric($total);
        $area = $this->numeric($area);
        if (bccomp($area, '0', 6) <= 0) {
            throw new DomainConflictException('مساحت برای محاسبه مبلغ هر متر باید بیشتر از صفر باشد.');
        }

        return $this->roundHalfUp(bcdiv($total, $area, 6));
    }

    public function maximumRent(string $deposit, string $rent, string $minimumDeposit): string
    {
        $deposit = $this->numeric($deposit);
        $rent = $this->numeric($rent);
        $minimumDeposit = $this->numeric($minimumDeposit);
        if (bccomp($minimumDeposit, $deposit, 2) > 0) {
            throw new DomainConflictException('حداقل ودیعه نمی‌تواند از ودیعه اولیه بیشتر باشد.');
        }

        return $this->convertedRentAtDeposit($deposit, $rent, $minimumDeposit);
    }

    public function convertedRentAtDeposit(string $initialDeposit, string $initialRent, string $deposit): string
    {
        $initialDeposit = $this->numeric($initialDeposit);
        $initialRent = $this->numeric($initialRent);
        $deposit = $this->numeric($deposit);
        $releasedDeposit = bcsub($initialDeposit, $deposit, 2);
        $converted = bcdiv(bcmul($releasedDeposit, self::RENT_PER_STEP, 2), self::DEPOSIT_STEP, 6);

        return $this->roundHalfUp(bcadd($initialRent, $converted, 6));
    }

    public function convertedRentForDeposit(string $initialDeposit, string $initialRent, string $targetDeposit): string
    {
        $initialDeposit = $this->numeric($initialDeposit);
        $initialRent = $this->numeric($initialRent);
        $targetDeposit = $this->numeric($targetDeposit);

        if (bccomp($targetDeposit, $initialDeposit, 6) > 0) {
            throw new DomainConflictException('ودیعهٔ تبدیل‌شده نمی‌تواند از ودیعهٔ اولیه بیشتر باشد.');
        }

        $releasedDeposit = bcsub($initialDeposit, $targetDeposit, 6);
        $converted = bcdiv(bcmul($releasedDeposit, self::RENT_PER_STEP, 6), self::DEPOSIT_STEP, 6);

        return $this->roundHalfUp(bcadd($initialRent, $converted, 6));
    }

    /** @param numeric-string $value */
    private function roundHalfUp(string $value): string
    {
        return bcadd($value, '0.5', 0);
    }

    /** @return numeric-string */
    private function numeric(string $value): string
    {
        if (! is_numeric($value)) {
            throw new DomainConflictException('مبلغ یا مساحت واردشده معتبر نیست.');
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

final class RelatedMatchPresentation
{
    public static function number(int|float|string|null $value): string
    {
        return strtr($value === null ? '—' : (string) $value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫',
        ]);
    }

    public static function summary(int $count, int $unreadCount): string
    {
        return self::number($count).' تطبیق · '.self::number($unreadCount).' جدید';
    }

    public static function accessibleSummary(int $count, int $unreadCount): string
    {
        return self::number($count).' تطبیق، '.self::number($unreadCount).' اعلان خوانده‌نشده برای شما';
    }
}

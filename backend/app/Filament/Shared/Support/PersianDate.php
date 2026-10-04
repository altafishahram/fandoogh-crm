<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use InvalidArgumentException;

final class PersianDate
{
    public const DISPLAY_PATTERN = 'd MMMM y';

    public static function format(DateTimeInterface|string|null $value, string $timezone = 'Asia/Tehran'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value, $timezone);
        $formatter = self::formatter(self::DISPLAY_PATTERN, $timezone);
        $formatted = $formatter->format($date->setTimezone($timezone));

        if (! is_string($formatted)) {
            throw new InvalidArgumentException('نمایش تاریخ شمسی امکان‌پذیر نیست.');
        }

        return $formatted;
    }

    public static function parse(string $value, string $timezone = 'Asia/Tehran'): CarbonImmutable
    {
        $normalized = trim(str_replace(['ي', 'ك'], ['ی', 'ک'], $value));
        foreach ([self::DISPLAY_PATTERN, 'yyyy/MM/dd', 'yyyy-M-d'] as $pattern) {
            $formatter = self::formatter($pattern, $timezone);
            $position = 0;
            $timestamp = $formatter->parse($normalized, $position);
            if ($timestamp !== false && $position === mb_strlen($normalized)) {
                return CarbonImmutable::createFromTimestamp($timestamp, $timezone)->startOfDay();
            }
        }

        throw new InvalidArgumentException('تاریخ شمسی باید مانند «۱۷ مرداد ۱۴۰۵» وارد شود.');
    }

    private static function formatter(string $pattern, string $timezone): IntlDateFormatter
    {
        $formatter = new IntlDateFormatter(
            'fa_IR@calendar=persian;numbers=arabext',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $timezone,
            IntlDateFormatter::TRADITIONAL,
            $pattern,
        );

        return $formatter;
    }
}

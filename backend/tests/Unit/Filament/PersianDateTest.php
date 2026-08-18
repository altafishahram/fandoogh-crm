<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\Shared\Support\PersianDate;
use PHPUnit\Framework\TestCase;

final class PersianDateTest extends TestCase
{
    public function test_it_formats_and_parses_the_approved_persian_date(): void
    {
        self::assertSame('۱۷ مرداد ۱۴۰۵', PersianDate::format('2026-08-08'));
        self::assertSame('2026-08-08', PersianDate::parse('۱۷ مرداد ۱۴۰۵')->toDateString());
    }

    public function test_it_handles_the_end_of_a_persian_leap_year(): void
    {
        self::assertSame('۳۰ اسفند ۱۴۰۳', PersianDate::format('2025-03-20'));
        self::assertSame('2025-03-20', PersianDate::parse('۳۰ اسفند ۱۴۰۳')->toDateString());
    }
}

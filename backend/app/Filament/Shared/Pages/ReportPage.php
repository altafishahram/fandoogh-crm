<?php

declare(strict_types=1);

namespace App\Filament\Shared\Pages;

use App\Application\Report\Data\ReportPeriod;
use App\Application\Report\Services\ReportService;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Filament\Shared\Support\PersianDate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use InvalidArgumentException;

abstract class ReportPage extends Page
{
    protected string $view = 'filament.shared.report';

    public function getViewData(): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canReport($user), 403);
        $timezone = (string) ($user->agency()->value('timezone') ?? 'UTC');
        $today = CarbonImmutable::today($timezone);
        $from = (string) request()->query('from', PersianDate::format($today->subDays(29), $timezone));
        $to = (string) request()->query('to', PersianDate::format($today, $timezone));
        $error = '';
        $report = [];
        try {
            $fromGregorian = PersianDate::parse($from, $timezone)->toDateString();
            $toGregorian = PersianDate::parse($to, $timezone)->toDateString();
            $report = app(ReportService::class)->for(
                $user, new ReportPeriod($fromGregorian, $toGregorian, $timezone),
            );
        } catch (DomainConflictException|InvalidArgumentException) {
            $error = 'بازه گزارش معتبر نیست؛ تاریخ را مانند «۱۷ مرداد ۱۴۰۵» وارد کنید.';
        }

        return compact('from', 'to', 'report', 'error');
    }

    private function canReport(User $user): bool
    {
        return match ($user->roleName()) {
            RoleName::AgencyManager => $user->can(PermissionName::ReportsAgencyView->value),
            RoleName::Agent => $user->can(PermissionName::ReportsOwnView->value),
            RoleName::SuperAdmin => false,
        };
    }
}

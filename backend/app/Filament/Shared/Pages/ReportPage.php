<?php

declare(strict_types=1);

namespace App\Filament\Shared\Pages;

use App\Application\Report\Data\ReportPeriod;
use App\Application\Report\Services\ReportService;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Validator;

abstract class ReportPage extends Page
{
    protected string $view = 'filament.shared.report';

    public function getViewData(): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canReport($user), 403);
        $timezone = (string) ($user->agency()->value('timezone') ?? 'UTC');
        $today = CarbonImmutable::today($timezone);
        $from = (string) request()->query('from', $today->subDays(29)->toDateString());
        $to = (string) request()->query('to', $today->toDateString());
        $validator = Validator::make(compact('from', 'to'), [
            'from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d'],
        ]);
        $error = $validator->errors()->first();
        $report = [];
        if ($error === '') {
            try {
                $report = app(ReportService::class)->for(
                    $user, new ReportPeriod($from, $to, $timezone),
                );
            } catch (DomainConflictException $exception) {
                $error = 'بازه گزارش معتبر نیست یا امکان تهیه این گزارش وجود ندارد.';
            }
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

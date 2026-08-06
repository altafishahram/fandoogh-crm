<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Application\Property\Contracts\PropertyRepositoryContract;
use App\Application\Property\Data\ChangePropertyStatusData;
use App\Application\Shared\Services\OptimisticLock;
use App\Domain\Property\Enums\PropertyHistoryAction;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\User\Enums\RoleName;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ChangePropertyStatusService
{
    public function __construct(
        private PropertyRepositoryContract $properties,
        private PropertyInvariantValidator $validator,
        private OptimisticLock $optimisticLock,
        private PropertyHistoryWriter $history,
    ) {}

    public function execute(User $actor, Property $property, ChangePropertyStatusData $data): Property
    {
        return DB::transaction(function () use ($actor, $property, $data): Property {
            $locked = $this->properties->lock((int) $property->getKey());
            $this->optimisticLock->assertCurrent($locked, $data->expectedUpdatedAt);
            $from = $locked->status;
            $to = $data->status;
            $this->validateTransition($actor, $locked, $from, $to, $data->reason);

            if ($to === PropertyStatus::Sold && $locked->transaction_type !== TransactionType::Sale) {
                throw new DomainConflictException('فقط ملک با معامله فروش می‌تواند فروخته شود.');
            }
            if ($to === PropertyStatus::Rented && $locked->transaction_type !== TransactionType::Rent) {
                throw new DomainConflictException('فقط ملک با معامله اجاره می‌تواند اجاره داده شود.');
            }

            $this->validator->commercial($locked->getAttributes());
            $locked->status = $to;
            $locked->closed_at = in_array($to, [PropertyStatus::Sold, PropertyStatus::Rented], true)
                ? ($data->closedAt ?? CarbonImmutable::now()) : null;
            $locked->archived_at = $to === PropertyStatus::Archived ? CarbonImmutable::now() : null;
            $locked = $this->properties->save($locked);
            $action = match (true) {
                $to === PropertyStatus::Archived => PropertyHistoryAction::Archived,
                $from === PropertyStatus::Archived => PropertyHistoryAction::Unarchived,
                default => PropertyHistoryAction::StatusChanged,
            };
            $this->history->write($locked, $actor, $action, $from, $to, reason: $data->reason);

            return $locked;
        });
    }

    private function validateTransition(
        User $actor,
        Property $property,
        PropertyStatus $from,
        PropertyStatus $to,
        ?string $reason,
    ): void {
        $manager = $actor->roleName() === RoleName::AgencyManager;
        $allowed = match ($from) {
            PropertyStatus::Available => [PropertyStatus::Reserved, PropertyStatus::Sold, PropertyStatus::Rented, PropertyStatus::Archived],
            PropertyStatus::Reserved => [PropertyStatus::Available, PropertyStatus::Sold, PropertyStatus::Rented, PropertyStatus::Archived],
            PropertyStatus::Sold, PropertyStatus::Rented => [PropertyStatus::Available, PropertyStatus::Archived],
            PropertyStatus::Archived => [PropertyStatus::Available],
        };

        if (! in_array($to, $allowed, true)) {
            throw new DomainConflictException('تغییر وضعیت درخواستی برای این ملک مجاز نیست.');
        }

        if (! $manager && ($property->assigned_agent_id !== $actor->getKey()
            || in_array($to, [PropertyStatus::Archived], true)
            || in_array($from, [PropertyStatus::Sold, PropertyStatus::Rented, PropertyStatus::Archived], true))) {
            throw new DomainConflictException('کارشناس اجازه این تغییر وضعیت را ندارد.');
        }

        $requiresReason = $to === PropertyStatus::Archived
            || ($from === PropertyStatus::Reserved && $to === PropertyStatus::Available)
            || (in_array($from, [PropertyStatus::Sold, PropertyStatus::Rented], true) && $to === PropertyStatus::Available);

        if ($requiresReason && trim((string) $reason) === '') {
            throw new DomainConflictException('ثبت دلیل برای این تغییر وضعیت الزامی است.');
        }
    }
}

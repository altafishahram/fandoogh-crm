<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Application\Customer\Contracts\CustomerRepositoryContract;
use App\Application\Customer\Data\UpdateCustomerData;
use App\Application\Matching\Services\MatchingRebuildDispatcher;
use App\Application\Shared\Services\AgentAssignmentValidator;
use App\Application\Shared\Services\OptimisticLock;
use App\Domain\Customer\Enums\CustomerHistoryAction;
use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\Tenancy\TenantContext;
use App\Domain\User\Enums\RoleName;
use App\Models\Customer;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCustomerService
{
    public function __construct(
        private CustomerRepositoryContract $customers,
        private CustomerInvariantValidator $validator,
        private AgentAssignmentValidator $agents,
        private OptimisticLock $optimisticLock,
        private TenantContext $tenant,
        private CustomerHistoryWriter $history,
        private MatchingRebuildDispatcher $matching,
    ) {}

    public function execute(User $actor, Customer $customer, UpdateCustomerData $data): Customer
    {
        $updated = DB::transaction(function () use ($actor, $customer, $data): Customer {
            $locked = $this->customers->lock((int) $customer->getKey());
            if ($data->expectedVersion !== null) {
                $this->optimisticLock->assertVersion($locked, $data->expectedVersion);
            } else {
                $this->optimisticLock->assertCurrent($locked, $data->expectedUpdatedAt);
            }
            $attributes = $data->attributes;

            if ($actor->roleName() === RoleName::Agent && array_key_exists('assigned_agent_id', $attributes)) {
                throw new DomainConflictException('کارشناس اجازه واگذاری مشتری به کارشناس دیگر را ندارد.');
            }
            if (array_key_exists('assigned_agent_id', $attributes) && $attributes['assigned_agent_id'] !== null) {
                $this->agents->validate((int) $attributes['assigned_agent_id'], $this->tenant->agencyId());
            }

            $merged = array_merge($locked->attributesToArray(), $attributes);
            $this->validator->validate($merged);
            $fromStatus = $locked->status;
            $toStatus = $attributes['status'] ?? $fromStatus;
            $toStatus = $toStatus instanceof CustomerStatus ? $toStatus : CustomerStatus::from((string) $toStatus);
            $this->validateTransition($actor, $fromStatus, $toStatus, $data->reason);

            if ($toStatus === CustomerStatus::Converted) {
                $propertyId = $attributes['converted_property_id'] ?? $locked->converted_property_id;
                if ($propertyId === null) {
                    throw new DomainConflictException('برای مشتری نهایی‌شده باید ملک مربوط مشخص شود.');
                }
                $property = Property::query()->findOrFail((int) $propertyId);
                $intent = $attributes['intent'] ?? $locked->intent;
                $intent = $intent instanceof CustomerIntent ? $intent : CustomerIntent::from((string) $intent);
                $this->validator->conversion($property, $intent, $actor);
                $attributes['converted_property_id'] = $property->getKey();
            } else {
                $attributes['converted_property_id'] = null;
            }

            $attributes['status'] = $toStatus;
            $before = $locked->getAttributes();
            $locked->fill($attributes);
            $locked->matching_eligible_at = now();
            $locked->lock_version = (int) $locked->lock_version + 1;

            $locked = $this->customers->save($locked);
            $changed = [];
            foreach ($attributes as $field => $value) {
                $old = $before[$field] ?? null;
                $new = $locked->getRawOriginal($field);
                if ($old !== $new) {
                    $changed[$field] = ['old' => $old, 'new' => $new];
                }
            }
            $action = $fromStatus === $toStatus
                ? CustomerHistoryAction::Updated
                : CustomerHistoryAction::StatusChanged;
            $this->history->write(
                $locked, $actor, $action, $fromStatus, $toStatus,
                $changed === [] ? null : $changed, $data->reason,
            );

            return $locked;
        });

        $this->matching->dispatchForAgency((int) $updated->agency_id);

        return $updated;
    }

    private function validateTransition(
        User $actor,
        CustomerStatus $from,
        CustomerStatus $to,
        ?string $reason,
    ): void {
        if ($from === $to) {
            return;
        }

        $allowed = match ($from) {
            CustomerStatus::Active => [
                CustomerStatus::Finalized, CustomerStatus::Withdrawn, CustomerStatus::TransactedElsewhere,
                CustomerStatus::Inactive, CustomerStatus::Lost, CustomerStatus::Converted,
            ],
            CustomerStatus::Finalized, CustomerStatus::Withdrawn, CustomerStatus::TransactedElsewhere,
            CustomerStatus::Inactive, CustomerStatus::Lost, CustomerStatus::Converted => [CustomerStatus::Active],
        };

        if ($to === CustomerStatus::Active && trim((string) $reason) === '') {
            throw new DomainConflictException('برای بازگرداندن مشتری به وضعیت فعال، ثبت دلیل الزامی است.');
        }

        if (! in_array($to, $allowed, true)
            || ($from === CustomerStatus::Converted && $actor->roleName() !== RoleName::AgencyManager)) {
            throw new DomainConflictException('تغییر وضعیت درخواستی برای این مشتری مجاز نیست.');
        }
    }
}

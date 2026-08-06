<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Application\Customer\Contracts\CustomerRepositoryContract;
use App\Application\Customer\Data\UpdateCustomerData;
use App\Application\Shared\Services\AgentAssignmentValidator;
use App\Application\Shared\Services\OptimisticLock;
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
    ) {}

    public function execute(User $actor, Customer $customer, UpdateCustomerData $data): Customer
    {
        return DB::transaction(function () use ($actor, $customer, $data): Customer {
            $locked = $this->customers->lock((int) $customer->getKey());
            $this->optimisticLock->assertCurrent($locked, $data->expectedUpdatedAt);
            $attributes = $data->attributes;

            if ($actor->roleName() === RoleName::Agent && array_key_exists('assigned_agent_id', $attributes)) {
                throw new DomainConflictException('کارشناس اجازه واگذاری مشتری به کارشناس دیگر را ندارد.');
            }
            if (array_key_exists('assigned_agent_id', $attributes)) {
                $this->agents->validate((int) $attributes['assigned_agent_id'], $this->tenant->agencyId());
            }

            $merged = array_merge($locked->attributesToArray(), $attributes);
            $this->validator->validate($merged);
            $fromStatus = $locked->status;
            $toStatus = $attributes['status'] ?? $fromStatus;
            $toStatus = $toStatus instanceof CustomerStatus ? $toStatus : CustomerStatus::from((string) $toStatus);
            $this->validateTransition($actor, $fromStatus, $toStatus);

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
            $locked->fill($attributes);

            return $this->customers->save($locked);
        });
    }

    private function validateTransition(User $actor, CustomerStatus $from, CustomerStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        $allowed = match ($from) {
            CustomerStatus::Active => [CustomerStatus::Inactive, CustomerStatus::Lost, CustomerStatus::Converted],
            CustomerStatus::Inactive => [CustomerStatus::Active, CustomerStatus::Lost],
            CustomerStatus::Lost => [CustomerStatus::Active],
            CustomerStatus::Converted => [CustomerStatus::Active],
        };

        if (! in_array($to, $allowed, true)
            || ($from === CustomerStatus::Converted && $actor->roleName() !== RoleName::AgencyManager)) {
            throw new DomainConflictException('تغییر وضعیت درخواستی برای این مشتری مجاز نیست.');
        }
    }
}

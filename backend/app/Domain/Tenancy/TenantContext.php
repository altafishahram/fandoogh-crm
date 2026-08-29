<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Exceptions\AgencyInactiveException;
use App\Domain\Tenancy\Exceptions\InactiveUserException;
use App\Domain\Tenancy\Exceptions\MissingTenantContextException;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Log;

final class TenantContext
{
    private ?Agency $agency = null;

    private ?User $user = null;

    private bool $established = false;

    public function establish(User $user): void
    {
        $this->clear();

        if (! $user->exists || ! $user->is_active || $user->trashed()) {
            throw new InactiveUserException('حساب کاربر واردشده غیرفعال است.');
        }

        $roles = $user->roles()->pluck('name');

        if ($roles->count() !== 1) {
            throw new MissingTenantContextException('کاربر باید دقیقاً یک نقش داشته باشد.');
        }

        $role = RoleName::tryFrom((string) $roles->first());

        if ($role === null) {
            throw new MissingTenantContextException('نقش کاربر پشتیبانی نمی‌شود.');
        }

        if ($role === RoleName::SuperAdmin) {
            if ($user->agency_id !== null) {
                throw new MissingTenantContextException('کاربر مدیریت کل نمی‌تواند عضو آژانس باشد.');
            }

            $this->user = $user;
            $this->established = true;
            Log::withContext(['user_id' => $user->getKey()]);

            return;
        }

        if ($user->agency_id === null) {
            throw new MissingTenantContextException('برای این کاربر آژانسی ثبت نشده است.');
        }

        $agency = Agency::query()->find($user->agency_id);

        if ($agency === null || ! $agency->is_active) {
            throw new AgencyInactiveException('دسترسی آژانس غیرفعال شده است.');
        }

        $this->user = $user;
        $this->agency = $agency;
        $this->established = true;

        Log::withContext([
            'user_id' => $user->getKey(),
            'agency_id' => $agency->getKey(),
        ]);
    }

    public function establishAgency(Agency $agency): void
    {
        $this->clear();

        if (! $agency->exists || ! $agency->is_active) {
            throw new AgencyInactiveException('دسترسی آژانس غیرفعال شده است.');
        }

        $this->agency = $agency;
        $this->established = true;

        Log::withContext(['agency_id' => $agency->getKey(), 'user_id' => null]);
    }

    /** @template TReturn
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runForAgency(Agency $agency, Closure $callback): mixed
    {
        $previousAgency = $this->agency;
        $previousUser = $this->user;
        $previousEstablished = $this->established;

        $this->establishAgency($agency);

        try {
            return $callback();
        } finally {
            $this->agency = $previousAgency;
            $this->user = $previousUser;
            $this->established = $previousEstablished;
        }
    }

    public function agency(): Agency
    {
        if (! $this->established || $this->agency === null) {
            throw new MissingTenantContextException('آژانس جاری برای این عملیات مشخص نشده است.');
        }

        return $this->agency;
    }

    public function agencyId(): int
    {
        return (int) $this->agency()->getKey();
    }

    public function user(): User
    {
        if (! $this->established || $this->user === null) {
            throw new MissingTenantContextException('کاربر جاری برای این عملیات مشخص نشده است.');
        }

        return $this->user;
    }

    public function hasAgency(): bool
    {
        return $this->established && $this->agency !== null;
    }

    public function clear(): void
    {
        $this->agency = null;
        $this->user = null;
        $this->established = false;
    }
}

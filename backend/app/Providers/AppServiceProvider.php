<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Agency\Contracts\AgencyRepositoryContract;
use App\Application\Customer\Contracts\CustomerRepositoryContract;
use App\Application\Owner\Contracts\OwnerRepositoryContract;
use App\Application\Property\Contracts\PropertyRepositoryContract;
use App\Application\User\Contracts\UserIdentityRepositoryContract;
use App\Domain\Tenancy\TenantContext;
use App\Infrastructure\Persistence\EloquentAgencyRepository;
use App\Infrastructure\Persistence\EloquentCustomerRepository;
use App\Infrastructure\Persistence\EloquentOwnerRepository;
use App\Infrastructure\Persistence\EloquentPropertyRepository;
use App\Infrastructure\Persistence\EloquentUserIdentityRepository;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\MatchNotification;
use App\Models\Owner;
use App\Models\Property;
use App\Models\PropertyNote;
use App\Models\PublicUser;
use App\Models\SavedFilter;
use App\Models\User;
use App\Policies\AgencyPolicy;
use App\Policies\CustomerNotePolicy;
use App\Policies\CustomerPolicy;
use App\Policies\MatchNotificationPolicy;
use App\Policies\OwnerPolicy;
use App\Policies\PropertyNotePolicy;
use App\Policies\PropertyPolicy;
use App\Policies\SavedFilterPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->bind(AgencyRepositoryContract::class, EloquentAgencyRepository::class);
        $this->app->bind(OwnerRepositoryContract::class, EloquentOwnerRepository::class);
        $this->app->bind(PropertyRepositoryContract::class, EloquentPropertyRepository::class);
        $this->app->bind(CustomerRepositoryContract::class, EloquentCustomerRepository::class);
        $this->app->bind(UserIdentityRepositoryContract::class, EloquentUserIdentityRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(Agency::class, AgencyPolicy::class);
        Gate::policy(Owner::class, OwnerPolicy::class);
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(PropertyNote::class, PropertyNotePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerNote::class, CustomerNotePolicy::class);
        Gate::policy(MatchNotification::class, MatchNotificationPolicy::class);
        Gate::policy(SavedFilter::class, SavedFilterPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        RateLimiter::for('api', static function (Request $request): Limit {
            $user = $request->user();
            $key = $user instanceof User ? 'user:'.$user->getKey()
                : ($user instanceof PublicUser ? 'public:'.$user->getKey() : 'ip:'.$request->ip());

            return Limit::perMinute(60)->by((string) $key);
        });
    }
}

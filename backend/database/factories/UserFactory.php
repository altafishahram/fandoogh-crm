<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/** @extends Factory<User> */
final class UserFactory extends Factory
{
    protected $model = User::class;

    private static ?string $password = null;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory()->active(),
            'name' => fake()->name(),
            'email' => mb_strtolower(fake()->unique()->safeEmail()),
            'phone' => '+989'.fake()->numerify('#########'),
            'password' => self::$password ??= Hash::make('Test-password-123!'),
            'is_active' => true,
            'must_change_password' => false,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(
            fn (User $user) => $this->assignRole($user, RoleName::Agent),
        );
    }

    public function superAdmin(): static
    {
        return $this
            ->state(fn (): array => ['agency_id' => null])
            ->afterCreating(fn (User $user) => $this->assignRole($user, RoleName::SuperAdmin));
    }

    public function agencyManager(Agency $agency): static
    {
        return $this
            ->state(fn (): array => ['agency_id' => $agency->getKey()])
            ->afterCreating(fn (User $user) => $this->assignRole($user, RoleName::AgencyManager));
    }

    public function agent(Agency $agency): static
    {
        return $this->state(fn (): array => ['agency_id' => $agency->getKey()]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn (): array => ['must_change_password' => true]);
    }

    private function assignRole(User $user, RoleName $roleName): void
    {
        Role::findOrCreate($roleName->value, 'web');
        $user->syncRoles([$roleName->value]);
        $user->syncPermissions($roleName === RoleName::Agent
            ? array_map(
                static fn (PermissionName $permission): string => $permission->value,
                RoleName::agentDefaultPermissions(),
            )
            : []);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Agency\Data\ProvisionAgencyData;
use App\Application\Agency\Services\ActivateAgencyService;
use App\Application\Agency\Services\ProvisionAgencyService;
use App\Application\Agency\Services\SuspendAgencyService;
use App\Application\User\Data\CreateUserData;
use App\Application\User\Services\CreateUserService;
use App\Application\User\Services\DeactivateUserService;
use App\Application\User\Services\ResetUserPasswordService;
use App\Domain\Agency\Exceptions\AgencyActivationException;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Tests\Support\IdentityTestCase;

final class IdentityServicesTest extends IdentityTestCase
{
    public function test_super_admin_provisions_agency_and_settings_atomically(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $agency = app(ProvisionAgencyService::class)->execute($superAdmin, $this->agencyData());

        self::assertFalse($agency->is_active);
        $this->assertDatabaseHas('agencies', [
            'id' => $agency->getKey(),
            'slug' => 'north-tehran',
        ]);
        $this->assertDatabaseHas('agency_settings', [
            'agency_id' => $agency->getKey(),
            'property_code_prefix' => 'NTH',
            'next_property_sequence' => 1,
        ]);
    }

    public function test_agency_prefix_is_rejected_in_persian_before_database_write(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        try {
            app(ProvisionAgencyService::class)->execute($superAdmin, $this->agencyData('عباسی'));
            self::fail('پیشوند نامعتبر پیش از ذخیره رد نشد.');
        } catch (DomainConflictException $exception) {
            self::assertSame(
                'پیشوند کد ملک باید شامل ۲ تا ۱۰ حرف بزرگ انگلیسی یا عدد باشد.',
                $exception->getMessage(),
            );
            $this->assertDatabaseCount('agencies', 0);
        }
    }

    public function test_agency_requires_an_active_manager_before_activation(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $agency = Agency::factory()->create();
        $service = app(ActivateAgencyService::class);

        try {
            $service->execute($superAdmin, $agency);
            self::fail('Agency activated without a manager.');
        } catch (AgencyActivationException) {
            $agency->refresh();
            self::assertFalse($agency->is_active);
        }

        User::factory()->agencyManager($agency)->create();
        $service->execute($superAdmin, $agency);

        $agency->refresh();
        self::assertTrue($agency->is_active);
        self::assertNotNull($agency->activated_at);
    }

    public function test_user_creation_enforces_platform_and_tenant_role_boundaries(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $agencyA = Agency::factory()->active()->create();
        $agencyB = Agency::factory()->active()->create();
        $managerA = User::factory()->agencyManager($agencyA)->create();
        $service = app(CreateUserService::class);

        $manager = $service->execute(
            $superAdmin,
            $agencyB,
            $this->userData(RoleName::AgencyManager, 'manager-b@example.test'),
        );
        $agent = $service->execute(
            $managerA,
            $agencyA,
            $this->userData(RoleName::Agent, 'agent-a@example.test'),
        );

        self::assertSame(RoleName::AgencyManager, $manager->roleName());
        self::assertSame($agencyB->getKey(), $manager->agency_id);
        self::assertSame(RoleName::Agent, $agent->roleName());
        self::assertTrue($agent->must_change_password);

        $this->expectException(AuthorizationException::class);
        $service->execute(
            $managerA,
            $agencyB,
            $this->userData(RoleName::Agent, 'cross-tenant@example.test'),
        );
    }

    public function test_suspension_deactivation_and_password_reset_revoke_tokens(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $agency = Agency::factory()->active()->create();
        $manager = User::factory()->agencyManager($agency)->create();
        $agent = User::factory()->agent($agency)->create();
        $agent->createToken('phone', ['mobile'], now()->addDays(30));

        app(ResetUserPasswordService::class)->execute($manager, $agent, 'Temporary-password-789!');

        $agent->refresh();
        self::assertTrue($agent->must_change_password);
        self::assertTrue(Hash::check('Temporary-password-789!', $agent->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $agent->createToken('phone', ['mobile'], now()->addDays(30));
        app(DeactivateUserService::class)->execute($manager, $agent);
        $agent->refresh();
        self::assertFalse($agent->is_active);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $manager->createToken('manager-device', ['mobile'], now()->addDays(30));
        app(SuspendAgencyService::class)->execute($superAdmin, $agency);
        $agency->refresh();
        self::assertFalse($agency->is_active);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function agencyData(string $propertyCodePrefix = 'nth'): ProvisionAgencyData
    {
        return new ProvisionAgencyData(
            name: 'North Tehran Realty',
            slug: 'north-tehran',
            email: 'Office@North.Example',
            phone: '+982188888888',
            addressLine1: 'Valiasr Street',
            addressLine2: null,
            city: 'Tehran',
            province: 'Tehran',
            postalCode: '1234567890',
            countryCode: 'ir',
            timezone: 'Asia/Tehran',
            locale: 'fa-IR',
            currencyCode: 'irr',
            propertyCodePrefix: $propertyCodePrefix,
        );
    }

    private function userData(RoleName $role, string $email): CreateUserData
    {
        return new CreateUserData(
            name: 'Test User',
            email: $email,
            phone: '+989121234567',
            temporaryPassword: 'Temporary-password-123!',
            role: $role,
        );
    }
}

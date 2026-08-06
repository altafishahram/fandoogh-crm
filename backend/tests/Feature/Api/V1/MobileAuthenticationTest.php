<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Support\IdentityTestCase;

final class MobileAuthenticationTest extends IdentityTestCase
{
    public function test_agent_can_update_own_mobile_profile(): void
    {
        $agency = Agency::factory()->active()->create();
        $agent = User::factory()->agent($agency)->create();
        Sanctum::actingAs($agent, ['mobile']);

        $this->patchJson('/api/v1/profile', [
            'name' => 'Agent Updated',
            'email' => 'UPDATED@EXAMPLE.TEST',
            'phone' => '+989121111111',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Agent Updated')
            ->assertJsonPath('data.email', 'updated@example.test')
            ->assertJsonPath('data.phone', '+989121111111');

        $this->assertDatabaseHas('users', [
            'id' => $agent->getKey(),
            'email' => 'updated@example.test',
        ]);
    }

    public function test_active_agent_can_login_and_read_profile_with_a_mobile_token(): void
    {
        $agency = Agency::factory()->active()->create();
        $agent = User::factory()->agent($agency)->create([
            'email' => 'agent@example.test',
        ]);

        $login = $this->postJson('/api/v1/auth/login', $this->credentials());

        $login
            ->assertCreated()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.must_change_password', false)
            ->assertJsonStructure(['data' => ['token', 'expires_at'], 'meta' => ['request_id']]);

        $token = (string) $login->json('data.token');
        self::assertNotSame('', $token);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $token]);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $agent->getKey())
            ->assertJsonPath('data.agency.id', $agency->getKey())
            ->assertJsonPath('data.agency.name', $agency->name)
            ->assertJsonFragment(['permissions' => collect(PermissionName::cases())
                ->filter(static fn (PermissionName $permission): bool => in_array(
                    $permission,
                    RoleName::Agent->permissions(),
                    true,
                ))
                ->map(static fn (PermissionName $permission): string => $permission->value)
                ->sort()
                ->values()
                ->all()])
            ->assertJsonMissingPath('data.agency_id');
    }

    public function test_login_failure_does_not_reveal_whether_the_email_exists(): void
    {
        $agency = Agency::factory()->active()->create();
        User::factory()->agent($agency)->create(['email' => 'agent@example.test']);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            ...$this->credentials(),
            'password' => 'wrong-password',
        ]);
        $missingEmail = $this->postJson('/api/v1/auth/login', [
            ...$this->credentials(),
            'email' => 'missing@example.test',
        ]);

        $wrongPassword->assertUnprocessable();
        $missingEmail->assertUnprocessable();
        self::assertSame($wrongPassword->json('error.code'), $missingEmail->json('error.code'));
        self::assertSame($wrongPassword->json('error.message'), $missingEmail->json('error.message'));
    }

    public function test_manager_inactive_user_and_suspended_agency_cannot_obtain_mobile_token(): void
    {
        $activeAgency = Agency::factory()->active()->create();
        $suspendedAgency = Agency::factory()->create();
        User::factory()->agencyManager($activeAgency)->create(['email' => 'manager@example.test']);
        User::factory()->agent($activeAgency)->inactive()->create(['email' => 'inactive@example.test']);
        User::factory()->agent($suspendedAgency)->create(['email' => 'suspended@example.test']);

        foreach (['manager@example.test', 'inactive@example.test', 'suspended@example.test'] as $email) {
            $this->postJson('/api/v1/auth/login', [...$this->credentials(), 'email' => $email])
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_is_limited_to_five_failed_attempts_per_email_and_ip(): void
    {
        $credentials = [...$this->credentials(), 'email' => 'limited@example.test'];
        $key = hash('sha256', 'limited@example.test|127.0.0.1');
        RateLimiter::clear($key);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', $credentials)->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', $credentials)
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_web_session_is_not_accepted_by_mobile_api(): void
    {
        $agency = Agency::factory()->active()->create();
        $agent = User::factory()->agent($agency)->create();

        $this->actingAs($agent, 'web')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_mobile_token_is_not_accepted_by_web_guard(): void
    {
        Route::get('/_test/login', static fn () => response()->json(['login' => true]))
            ->name('login');
        Route::middleware(['web', 'auth:web'])
            ->get('/_test/web-only', static fn () => response()->json(['ok' => true]));
        Route::getRoutes()->refreshNameLookups();
        $agency = Agency::factory()->active()->create();
        $agent = User::factory()->agent($agency)->create();
        $token = $agent->createToken('phone', ['mobile'], now()->addDays(30))->plainTextToken;

        $this->withToken($token)
            ->get('/_test/web-only')
            ->assertRedirect('/_test/login');
    }

    public function test_wrong_ability_and_expired_tokens_are_rejected(): void
    {
        $agency = Agency::factory()->active()->create();
        $agent = User::factory()->agent($agency)->create();
        $wrongAbility = $agent->createToken('wrong', ['other'], now()->addDays(30))->plainTextToken;
        $expired = $agent->createToken('expired', ['mobile'], now()->subMinute())->plainTextToken;

        $this->withToken($wrongAbility)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
        Auth::forgetGuards();
        $this->withToken($expired)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_current_token_and_logout_all_revokes_every_token(): void
    {
        $agency = Agency::factory()->active()->create();
        $agent = User::factory()->agent($agency)->create();
        $current = $agent->createToken('current', ['mobile'], now()->addDays(30))->plainTextToken;
        $other = $agent->createToken('other', ['mobile'], now()->addDays(30))->plainTextToken;

        $this->withToken($current)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($other)->postJson('/api/v1/auth/logout-all')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_required_password_change_replaces_all_tokens_and_unblocks_operations(): void
    {
        Route::middleware([
            'auth:sanctum',
            'abilities:mobile',
            'tenant',
            'password.changed',
        ])->get('/api/v1/_test/operation', static fn () => response()->json(['ok' => true]));
        $agency = Agency::factory()->active()->create();
        User::factory()->agent($agency)->mustChangePassword()->create([
            'email' => 'agent@example.test',
        ]);
        $login = $this->postJson('/api/v1/auth/login', $this->credentials())
            ->assertCreated()
            ->assertJsonPath('data.must_change_password', true);
        $oldToken = (string) $login->json('data.token');

        $this->withToken($oldToken)
            ->getJson('/api/v1/_test/operation')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PASSWORD_CHANGE_REQUIRED');
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertOk();

        $change = $this->withToken($oldToken)->putJson('/api/v1/profile/password', [
            'current_password' => 'Test-password-123!',
            'password' => 'New-password-456!',
            'password_confirmation' => 'New-password-456!',
            'device_name' => 'Pixel replacement',
        ])->assertOk()->assertJsonPath('data.must_change_password', false);
        $newToken = (string) $change->json('data.token');

        Auth::forgetGuards();
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        Auth::forgetGuards();
        $this->withToken($newToken)->getJson('/api/v1/_test/operation')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_authenticated_request_to_suspended_agency_receives_specific_error(): void
    {
        $agency = Agency::factory()->active()->create();
        $agent = User::factory()->agent($agency)->create();
        $token = $agent->createToken('phone', ['mobile'], now()->addDays(30))->plainTextToken;
        $agency->update(['is_active' => false]);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AGENCY_INACTIVE');
    }

    /** @return array{email: string, password: string, device_name: string} */
    private function credentials(): array
    {
        return [
            'email' => 'agent@example.test',
            'password' => 'Test-password-123!',
            'device_name' => 'Pixel 9',
        ];
    }
}

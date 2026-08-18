<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Application\User\Contracts\UserIdentityRepositoryContract;
use App\Application\User\Data\MobileTokenResult;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

final readonly class MobileAuthenticationService
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$1Eemf7Qzimidh79Et4qjp.nW.zm4.NhEYaWUEFmmPfDSAl3s.3LmG';

    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(private UserIdentityRepositoryContract $users) {}

    public function login(string $email, string $password, string $deviceName, string $ipAddress): MobileTokenResult
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $limiterKey = hash('sha256', $normalizedEmail.'|'.$ipAddress);

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_ATTEMPTS)) {
            throw new ThrottleRequestsException('', null, [
                'Retry-After' => (string) RateLimiter::availableIn($limiterKey),
            ]);
        }

        $user = $this->users->findForAuthentication($normalizedEmail);
        $passwordHash = $user instanceof User ? $user->password : self::DUMMY_PASSWORD_HASH;
        $passwordMatches = Hash::check($password, $passwordHash);

        if (! $user instanceof User || ! $passwordMatches || ! $this->isEligibleMobileUser($user)) {
            RateLimiter::hit($limiterKey, self::DECAY_SECONDS);

            throw new InvalidCredentialsException('ایمیل یا رمز عبور درست نیست.');
        }

        RateLimiter::clear($limiterKey);

        return DB::transaction(function () use ($user, $deviceName): MobileTokenResult {
            $expiresAt = CarbonImmutable::now()->addDays(30);

            $this->users->revokeExpiredMobileTokens($user);
            $user->setAttribute('last_login_at', CarbonImmutable::now());
            $this->users->save($user);

            $token = $user->createToken(trim($deviceName), ['mobile'], $expiresAt);

            return new MobileTokenResult($user, $token->plainTextToken, $expiresAt);
        });
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function logoutAll(User $user): void
    {
        $this->users->revokeAllMobileTokens($user);
    }

    public function profile(User $user): User
    {
        return $user->loadMissing(['agency', 'roles.permissions']);
    }

    private function isEligibleMobileUser(User $user): bool
    {
        if (! $user->is_active
            || $user->trashed()
            || $user->agency_id === null
            || $user->roles()->count() !== 1
            || ! $user->hasAnyRole([RoleName::Agent->value, RoleName::AgencyManager->value])) {
            return false;
        }

        return $user->agency()->where('is_active', true)->exists();
    }
}

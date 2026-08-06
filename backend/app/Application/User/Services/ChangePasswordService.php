<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Application\User\Contracts\UserIdentityRepositoryContract;
use App\Application\User\Data\MobileTokenResult;
use App\Domain\User\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class ChangePasswordService
{
    public function __construct(private UserIdentityRepositoryContract $users) {}

    public function execute(
        User $user,
        string $currentPassword,
        string $newPassword,
        string $deviceName,
    ): MobileTokenResult {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new InvalidCredentialsException('رمز عبور فعلی درست نیست.');
        }

        return DB::transaction(function () use ($user, $newPassword, $deviceName): MobileTokenResult {
            $expiresAt = CarbonImmutable::now()->addDays(30);

            $user->password = $newPassword;
            $user->must_change_password = false;
            $this->users->save($user);
            $this->users->revokeAllMobileTokens($user);

            $token = $user->createToken(trim($deviceName), ['mobile'], $expiresAt);

            return new MobileTokenResult($user, $token->plainTextToken, $expiresAt);
        });
    }
}

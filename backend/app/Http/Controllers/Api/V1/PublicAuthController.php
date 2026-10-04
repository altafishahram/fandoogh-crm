<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PublicUser;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class PublicAuthController extends Controller
{
    public function config(): mixed
    {
        return response()->json(['data' => ['mode' => config('marketplace.auth_mode'), 'temporary_code' => config('marketplace.auth_mode') === 'fixed_code' ? config('marketplace.fixed_code') : null]]);
    }

    public function login(Request $request): mixed
    {
        $phone = strtr((string) $request->input('phone'), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $phone = preg_replace('/[\s-]/', '', $phone) ?? '';
        if (str_starts_with($phone, '+98')) {
            $phone = '0'.substr($phone, 3);
        }
        $request->merge(['phone' => $phone]);
        $data = $request->validate(['phone' => ['required', 'regex:/^09[0-9]{9}$/'], 'code' => ['required', 'string', 'max:20'], 'device_name' => ['sometimes', 'string', 'max:100']]);
        $key = 'public-login:'.hash('sha256', $phone.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new ThrottleRequestsException;
        }
        RateLimiter::hit($key, 60);
        if (config('marketplace.auth_mode') !== 'fixed_code' || ! hash_equals((string) config('marketplace.fixed_code'), $data['code'])) {
            throw ValidationException::withMessages(['code' => ['کد ورود درست نیست.']]);
        }
        $user = PublicUser::query()->firstOrCreate(['phone' => $phone]);
        abort_unless($user->is_active, 403);
        RateLimiter::clear($key);
        $expires = now()->addDays(30);
        $token = $user->createToken($data['device_name'] ?? 'public-app', ['marketplace:public'], $expires);

        return response()->json(['data' => ['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $expires->toISOString(), 'user' => $this->identity($user)]], 201);
    }

    public function me(Request $request): mixed
    {
        $user = $request->user();
        abort_unless($user instanceof PublicUser && $user->is_active, 403);

        return response()->json(['data' => $this->identity($user)]);
    }

    public function logout(Request $request): mixed
    {
        $user = $request->user();
        abort_unless($user instanceof PublicUser, 403);
        $user->currentAccessToken()?->delete();

        return response()->noContent();
    }

    /** @return array{id: mixed, phone: string, phone_verified: bool} */
    private function identity(PublicUser $user): array
    {
        return ['id' => $user->getKey(), 'phone' => $user->phone, 'phone_verified' => $user->phone_verified_at !== null];
    }
}

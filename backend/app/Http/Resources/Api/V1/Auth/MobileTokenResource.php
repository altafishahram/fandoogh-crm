<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use App\Application\User\Data\MobileTokenResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MobileTokenResult */
final class MobileTokenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->expiresAt->utc()->toISOString(),
            'must_change_password' => $this->user->must_change_password,
        ];
    }
}

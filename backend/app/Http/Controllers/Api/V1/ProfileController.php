<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\V1\Auth\AuthenticatedUserResource;
use App\Models\User;

final class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): AuthenticatedUserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user->update($request->safe()->only(['name', 'email', 'phone']));
        $user->refresh();

        return new AuthenticatedUserResource($user->load('agency'));
    }
}

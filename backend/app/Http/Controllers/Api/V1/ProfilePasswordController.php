<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\User\Services\ChangePasswordService;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Http\Requests\Api\V1\Profile\ChangePasswordRequest;
use App\Http\Resources\Api\V1\Auth\MobileTokenResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class ProfilePasswordController extends Controller
{
    public function update(ChangePasswordRequest $request, ChangePasswordService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $service->execute(
            $user,
            (string) $request->validated('current_password'),
            (string) $request->validated('password'),
            (string) $request->validated('device_name'),
        );

        return (new MobileTokenResource($result))
            ->additional(['meta' => [
                'request_id' => (string) $request->attributes->get(AssignRequestId::ATTRIBUTE),
            ]])
            ->response();
    }
}

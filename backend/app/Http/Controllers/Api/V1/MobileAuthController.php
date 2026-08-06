<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\User\Services\MobileAuthenticationService;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Auth\AuthenticatedUserResource;
use App\Http\Resources\Api\V1\Auth\MobileTokenResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MobileAuthController extends Controller
{
    public function login(LoginRequest $request, MobileAuthenticationService $authentication): JsonResponse
    {
        $result = $authentication->login(
            (string) $request->validated('email'),
            (string) $request->validated('password'),
            (string) $request->validated('device_name'),
            (string) $request->ip(),
        );

        return (new MobileTokenResource($result))
            ->additional(['meta' => $this->metadata($request)])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function me(Request $request, MobileAuthenticationService $authentication): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new AuthenticatedUserResource($authentication->profile($user)))
            ->additional(['meta' => $this->metadata($request)])
            ->response();
    }

    public function logout(Request $request, MobileAuthenticationService $authentication): Response
    {
        /** @var User $user */
        $user = $request->user();
        $authentication->logout($user);

        return response()->noContent();
    }

    public function logoutAll(Request $request, MobileAuthenticationService $authentication): Response
    {
        /** @var User $user */
        $user = $request->user();
        $authentication->logoutAll($user);

        return response()->noContent();
    }

    /** @return array{request_id: string} */
    private function metadata(Request $request): array
    {
        return [
            'request_id' => (string) $request->attributes->get(AssignRequestId::ATTRIBUTE),
        ];
    }
}

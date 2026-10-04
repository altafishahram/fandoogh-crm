<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\User\Services\UpdateAgentPermissionsService;
use App\Domain\User\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\UpdateAgentPermissionsRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AgentPermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        if ($actor->roleName() !== RoleName::AgencyManager) {
            throw new AuthorizationException;
        }

        $agents = User::query()->role(RoleName::Agent->value)->with('permissions')->orderBy('name')->get();

        return response()->json(['data' => $agents->map(fn (User $agent): array => $this->resource($agent))]);
    }

    public function update(
        UpdateAgentPermissionsRequest $request,
        User $agent,
        UpdateAgentPermissionsService $service,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        /** @var list<string> $permissions */
        $permissions = $request->validated('permissions');

        return response()->json(['data' => $this->resource($service->execute($actor, $agent, $permissions))]);
    }

    /** @return array<string, mixed> */
    private function resource(User $agent): array
    {
        return [
            'id' => $agent->getKey(),
            'name' => $agent->name,
            'is_active' => $agent->is_active,
            'permissions' => $agent->getDirectPermissions()->pluck('name')->sort()->values()->all(),
        ];
    }
}

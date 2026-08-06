<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Owner\Services\CreateOwnerService;
use App\Application\Owner\Services\UpdateOwnerService;
use App\Domain\User\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Owner\StoreOwnerRequest;
use App\Http\Requests\Api\V1\Owner\UpdateOwnerRequest;
use App\Http\Resources\Api\V1\Owner\OwnerResource;
use App\Http\Resources\Api\V1\Owner\OwnerSummaryResource;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class OwnerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('viewAny', Owner::class);
        $queryText = trim((string) $request->query('q', ''));
        $query = Owner::query()->orderByDesc('updated_at');

        if ($user->roleName() === RoleName::Agent) {
            if (mb_strlen($queryText) < 2) {
                throw ValidationException::withMessages(['q' => ['Enter at least two characters.']]);
            }
            $query->whereHas('properties', fn ($properties) => $properties
                ->where('assigned_agent_id', $user->getKey()));
        }

        if ($queryText !== '') {
            $escaped = addcslashes($queryText, '%_\\');
            $query->where(function ($builder) use ($escaped): void {
                $builder->where('first_name', 'like', $escaped.'%')
                    ->orWhere('last_name', 'like', $escaped.'%')
                    ->orWhere('company_name', 'like', $escaped.'%')
                    ->orWhere('mobile', 'like', $escaped.'%')
                    ->orWhere('email', 'like', $escaped.'%');
            });
        }

        if ($user->roleName() === RoleName::Agent) {
            return OwnerSummaryResource::collection($query->limit(20)->get());
        }

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        return OwnerSummaryResource::collection($query->paginate($perPage));
    }

    public function store(StoreOwnerRequest $request, CreateOwnerService $service): JsonResponse
    {
        Gate::authorize('create', Owner::class);
        /** @var User $user */
        $user = $request->user();

        return (new OwnerResource($service->execute($user, $request->toData())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Owner $owner): OwnerResource
    {
        Gate::authorize('view', $owner);

        return new OwnerResource($owner);
    }

    public function update(
        UpdateOwnerRequest $request,
        Owner $owner,
        UpdateOwnerService $service,
    ): OwnerResource {
        Gate::authorize('update', $owner);
        /** @var User $user */
        $user = $request->user();

        return new OwnerResource($service->execute(
            $user,
            $owner,
            $request->toDataFor($owner),
            $request->expectedUpdatedAt(),
        ));
    }
}

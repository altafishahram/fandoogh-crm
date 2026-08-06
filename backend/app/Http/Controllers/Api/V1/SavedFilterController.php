<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\SavedFilter\Services\SavedFilterService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SavedFilter\SavedFilterRequest;
use App\Http\Resources\Api\V1\SavedFilter\SavedFilterResource;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class SavedFilterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SavedFilter::class);
        /** @var User $user */
        $user = $request->user();

        return SavedFilterResource::collection(
            SavedFilter::query()->where('user_id', $user->getKey())->orderBy('module')->orderBy('name')->get(),
        );
    }

    public function store(SavedFilterRequest $request, SavedFilterService $service): JsonResponse
    {
        Gate::authorize('create', SavedFilter::class);
        /** @var User $user */
        $user = $request->user();

        return (new SavedFilterResource($service->create($user, $request->toData())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        SavedFilterRequest $request,
        SavedFilter $savedFilter,
        SavedFilterService $service,
    ): SavedFilterResource {
        Gate::authorize('update', $savedFilter);
        /** @var User $user */
        $user = $request->user();

        return new SavedFilterResource($service->update($user, $savedFilter, $request->toData()));
    }

    public function destroy(Request $request, SavedFilter $savedFilter, SavedFilterService $service): Response
    {
        Gate::authorize('delete', $savedFilter);
        /** @var User $user */
        $user = $request->user();
        $service->delete($user, $savedFilter);

        return response()->noContent();
    }
}

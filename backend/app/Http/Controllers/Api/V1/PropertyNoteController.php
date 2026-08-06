<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Property\Services\PropertyNoteService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Shared\NoteRequest;
use App\Http\Resources\Api\V1\Shared\NoteResource;
use App\Models\Property;
use App\Models\PropertyNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class PropertyNoteController extends Controller
{
    public function index(Property $property): AnonymousResourceCollection
    {
        Gate::authorize('view', $property);

        return NoteResource::collection($property->notes()->orderByDesc('created_at')->paginate(50));
    }

    public function store(NoteRequest $request, Property $property, PropertyNoteService $service): JsonResponse
    {
        Gate::authorize('manageNotes', $property);
        /** @var User $user */
        $user = $request->user();

        return (new NoteResource($service->create($property, $user, (string) $request->validated('body'))))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        NoteRequest $request,
        Property $property,
        PropertyNote $note,
        PropertyNoteService $service,
    ): NoteResource {
        $this->ensureNested($property, $note);
        Gate::authorize('manageNotes', $property);
        Gate::authorize('update', $note);

        return new NoteResource($service->update($note, (string) $request->validated('body')));
    }

    public function destroy(
        Request $request,
        Property $property,
        PropertyNote $note,
        PropertyNoteService $service,
    ): Response {
        $this->ensureNested($property, $note);
        Gate::authorize('manageNotes', $property);
        Gate::authorize('delete', $note);
        $service->delete($note);

        return response()->noContent();
    }

    private function ensureNested(Property $property, PropertyNote $note): void
    {
        abort_unless($note->property_id === $property->getKey(), Response::HTTP_NOT_FOUND);
    }
}

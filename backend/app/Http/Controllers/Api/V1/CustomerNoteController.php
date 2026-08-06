<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Customer\Services\CustomerNoteService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Shared\NoteRequest;
use App\Http\Resources\Api\V1\Shared\NoteResource;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class CustomerNoteController extends Controller
{
    public function index(Customer $customer): AnonymousResourceCollection
    {
        Gate::authorize('view', $customer);

        return NoteResource::collection($customer->notes()->orderByDesc('created_at')->paginate(50));
    }

    public function store(NoteRequest $request, Customer $customer, CustomerNoteService $service): JsonResponse
    {
        Gate::authorize('manageNotes', $customer);
        /** @var User $user */
        $user = $request->user();

        return (new NoteResource($service->create($customer, $user, (string) $request->validated('body'))))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        NoteRequest $request,
        Customer $customer,
        CustomerNote $note,
        CustomerNoteService $service,
    ): NoteResource {
        $this->ensureNested($customer, $note);
        Gate::authorize('manageNotes', $customer);
        Gate::authorize('update', $note);

        return new NoteResource($service->update($note, (string) $request->validated('body')));
    }

    public function destroy(
        Customer $customer,
        CustomerNote $note,
        CustomerNoteService $service,
    ): Response {
        $this->ensureNested($customer, $note);
        Gate::authorize('manageNotes', $customer);
        Gate::authorize('delete', $note);
        $service->delete($note);

        return response()->noContent();
    }

    private function ensureNested(Customer $customer, CustomerNote $note): void
    {
        abort_unless($note->customer_id === $customer->getKey(), Response::HTTP_NOT_FOUND);
    }
}

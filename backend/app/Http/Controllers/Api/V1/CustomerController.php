<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Customer\Services\CreateCustomerService;
use App\Application\Customer\Services\UpdateCustomerService;
use App\Domain\User\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\Customer\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Customer::class);
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'string'],
            'intent' => ['nullable', 'string'], 'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $query = Customer::query()->orderByDesc('updated_at')->orderByDesc('id');
        if ($user->roleName() === RoleName::Agent) {
            $query->where('assigned_agent_id', $user->getKey());
        }
        foreach (['status', 'intent'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }
        if (isset($validated['q'])) {
            $escaped = addcslashes(trim($validated['q']), '%_\\');
            $query->where(fn ($builder) => $builder->where('first_name', 'like', $escaped.'%')
                ->orWhere('last_name', 'like', $escaped.'%')
                ->orWhere('mobile', 'like', $escaped.'%')
                ->orWhere('email', 'like', $escaped.'%'));
        }

        return CustomerResource::collection($query->paginate($validated['per_page'] ?? 25));
    }

    public function store(StoreCustomerRequest $request, CreateCustomerService $service): JsonResponse
    {
        Gate::authorize('create', Customer::class);
        /** @var User $user */
        $user = $request->user();

        return (new CustomerResource($service->execute($user, $request->toData())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Customer $customer): CustomerResource
    {
        Gate::authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        UpdateCustomerService $service,
    ): CustomerResource {
        Gate::authorize('update', $customer);
        /** @var User $user */
        $user = $request->user();

        return new CustomerResource($service->execute($user, $customer, $request->toData()));
    }
}

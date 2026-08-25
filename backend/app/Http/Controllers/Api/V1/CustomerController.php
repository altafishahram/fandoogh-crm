<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Customer\Services\CreateCustomerService;
use App\Application\Customer\Services\UpdateCustomerService;
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
            'property_type' => ['nullable', 'string'], 'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'area_min' => ['nullable', 'numeric', 'gte:0'],
            'area_max' => ['nullable', 'numeric', 'gte:area_min'],
            'budget_min' => ['nullable', 'numeric', 'gte:0'],
            'budget_max' => ['nullable', 'numeric', 'gte:budget_min'],
            'deposit_min' => ['nullable', 'numeric', 'gte:0'],
            'deposit_max' => ['nullable', 'numeric', 'gte:deposit_min'],
            'rent_min' => ['nullable', 'numeric', 'gte:0'],
            'rent_max' => ['nullable', 'numeric', 'gte:rent_min'],
            'bedrooms_min' => ['nullable', 'integer', 'between:0,255'],
            'parking_min' => ['nullable', 'integer', 'between:0,255'],
            'building_type' => ['nullable', 'string'], 'cabinet_type' => ['nullable', 'string'],
            'heating_type' => ['nullable', 'string'], 'cooling_type' => ['nullable', 'string'],
            'flooring_type' => ['nullable', 'string'], 'renovation_status' => ['nullable', 'string'],
            'building_orientation' => ['nullable', 'string'], 'deed_type' => ['nullable', 'string'],
            'has_parking' => ['nullable', 'boolean'], 'has_storage_room' => ['nullable', 'boolean'],
            'owner_resides' => ['nullable', 'boolean'], 'has_elevator' => ['nullable', 'boolean'],
            'has_balcony' => ['nullable', 'boolean'], 'has_master_bathroom' => ['nullable', 'boolean'],
            'has_loan' => ['nullable', 'boolean'], 'is_exchangeable' => ['nullable', 'boolean'],
            'has_pool' => ['nullable', 'boolean'], 'has_jacuzzi' => ['nullable', 'boolean'],
            'has_sauna' => ['nullable', 'boolean'], 'has_water' => ['nullable', 'boolean'],
            'has_electricity' => ['nullable', 'boolean'], 'has_gas' => ['nullable', 'boolean'],
            'accepts_rent_conversion' => ['nullable', 'boolean'],
            'order' => ['nullable', 'in:newest,oldest'],
        ]);
        $query = Customer::query();
        foreach (['status', 'intent'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }
        if (isset($validated['property_type'])) {
            $query->where('desired_property_type', $validated['property_type']);
        }
        if (isset($validated['city'])) {
            $query->where('desired_city', 'like', '%'.addcslashes(trim($validated['city']), '%_\\').'%');
        }
        if (isset($validated['district'])) {
            $query->where('desired_district', 'like', '%'.addcslashes(trim($validated['district']), '%_\\').'%');
        }
        if (isset($validated['area_min'])) {
            $query->where('min_area_sqm', '>=', $validated['area_min']);
        }
        if (isset($validated['area_max'])) {
            $query->where('max_area_sqm', '<=', $validated['area_max']);
        }
        foreach ([
            'budget_min' => ['budget_min', '>='], 'budget_max' => ['budget_max', '<='],
            'deposit_min' => ['rental_deposit_min', '>='], 'deposit_max' => ['rental_deposit_max', '<='],
            'rent_min' => ['rental_rent_min', '>='], 'rent_max' => ['rental_rent_max', '<='],
            'bedrooms_min' => ['min_bedrooms', '>='], 'parking_min' => ['min_parking_spaces', '>='],
        ] as $filter => [$column, $operator]) {
            if (isset($validated[$filter])) {
                $query->where($column, $operator, $validated[$filter]);
            }
        }
        foreach ([
            'building_type', 'cabinet_type', 'heating_type', 'cooling_type', 'flooring_type',
            'renovation_status', 'building_orientation', 'deed_type',
        ] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }
        foreach ([
            'has_parking', 'has_storage_room', 'owner_resides', 'has_elevator', 'has_balcony',
            'has_master_bathroom', 'has_loan', 'is_exchangeable', 'has_pool', 'has_jacuzzi',
            'has_sauna', 'has_water', 'has_electricity', 'has_gas', 'accepts_rent_conversion',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $query->where($field, (bool) $validated[$field]);
            }
        }
        if (isset($validated['q'])) {
            $escaped = addcslashes(trim($validated['q']), '%_\\');
            $query->where(fn ($builder) => $builder->where('full_name', 'like', '%'.$escaped.'%')
                ->orWhere('first_name', 'like', $escaped.'%')
                ->orWhere('last_name', 'like', $escaped.'%')
                ->orWhere('mobile', 'like', $escaped.'%')
                ->orWhere('email', 'like', $escaped.'%'));
        }
        ($validated['order'] ?? 'newest') === 'oldest'
            ? $query->orderBy('created_at')->orderBy('id')
            : $query->orderByDesc('created_at')->orderByDesc('id');

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
        $requestedStatus = $request->validated('status');
        if (is_string($requestedStatus) && $requestedStatus !== $customer->status->value) {
            Gate::authorize('changeStatus', $customer);
        }
        /** @var User $user */
        $user = $request->user();

        return new CustomerResource($service->execute($user, $customer, $request->toData()));
    }
}

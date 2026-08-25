<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Property\Services\ChangePropertyStatusService;
use App\Application\Property\Services\CreatePropertyService;
use App\Application\Property\Services\UpdatePropertyService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Property\ChangePropertyStatusRequest;
use App\Http\Requests\Api\V1\Property\StorePropertyRequest;
use App\Http\Requests\Api\V1\Property\UpdatePropertyRequest;
use App\Http\Resources\Api\V1\Property\PropertyResource;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class PropertyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Property::class);
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string'], 'property_type' => ['nullable', 'string'],
            'transaction_type' => ['nullable', 'string'], 'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'area_min' => ['nullable', 'numeric', 'gte:0'], 'area_max' => ['nullable', 'numeric', 'gte:area_min'],
            'price_min' => ['nullable', 'numeric', 'gte:0'], 'price_max' => ['nullable', 'numeric', 'gte:price_min'],
            'sale_price_min' => ['nullable', 'numeric', 'gte:0'],
            'sale_price_max' => ['nullable', 'numeric', 'gte:sale_price_min'],
            'deposit_min' => ['nullable', 'numeric', 'gte:0'],
            'deposit_max' => ['nullable', 'numeric', 'gte:deposit_min'],
            'rent_min' => ['nullable', 'numeric', 'gte:0'],
            'rent_max' => ['nullable', 'numeric', 'gte:rent_min'],
            'bedrooms_min' => ['nullable', 'integer', 'between:0,255'],
            'parking_min' => ['nullable', 'integer', 'between:0,255'],
            'delivery_status' => ['nullable', 'string'], 'building_type' => ['nullable', 'string'],
            'cabinet_type' => ['nullable', 'string'], 'heating_type' => ['nullable', 'string'],
            'cooling_type' => ['nullable', 'string'], 'flooring_type' => ['nullable', 'string'],
            'renovation_status' => ['nullable', 'string'], 'building_orientation' => ['nullable', 'string'],
            'deed_type' => ['nullable', 'string'],
            'has_storage_room' => ['nullable', 'boolean'], 'has_elevator' => ['nullable', 'boolean'],
            'has_balcony' => ['nullable', 'boolean'], 'has_master_bathroom' => ['nullable', 'boolean'],
            'has_loan' => ['nullable', 'boolean'], 'is_exchangeable' => ['nullable', 'boolean'],
            'has_pool' => ['nullable', 'boolean'], 'has_jacuzzi' => ['nullable', 'boolean'],
            'has_sauna' => ['nullable', 'boolean'], 'has_water' => ['nullable', 'boolean'],
            'has_electricity' => ['nullable', 'boolean'], 'has_gas' => ['nullable', 'boolean'],
            'can_aggregate' => ['nullable', 'boolean'],
            'order' => ['nullable', 'in:newest,oldest'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $query = Property::query()->with([
            'owners',
            'images' => static fn ($images) => $images->orderBy('sort_order'),
        ]);

        foreach (['status', 'property_type', 'transaction_type'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }
        if (isset($validated['city'])) {
            $query->where('city', 'like', '%'.addcslashes(trim($validated['city']), '%_\\').'%');
        }
        if (isset($validated['district'])) {
            $query->where('district', 'like', '%'.addcslashes(trim($validated['district']), '%_\\').'%');
        }
        if (isset($validated['area_min'])) {
            $query->where('area_sqm', '>=', $validated['area_min']);
        }
        if (isset($validated['area_max'])) {
            $query->where('area_sqm', '<=', $validated['area_max']);
        }
        if (isset($validated['price_min'])) {
            $query->whereRaw('COALESCE(sale_price, deposit_amount) >= ?', [$validated['price_min']]);
        }
        if (isset($validated['price_max'])) {
            $query->whereRaw('COALESCE(sale_price, deposit_amount) <= ?', [$validated['price_max']]);
        }
        foreach ([
            'sale_price_min' => ['sale_price', '>='], 'sale_price_max' => ['sale_price', '<='],
            'deposit_min' => ['deposit_amount', '>='], 'deposit_max' => ['deposit_amount', '<='],
            'rent_min' => ['monthly_rent', '>='], 'rent_max' => ['monthly_rent', '<='],
        ] as $filter => [$column, $operator]) {
            if (isset($validated[$filter])) {
                $query->where($column, $operator, $validated[$filter]);
            }
        }
        if (isset($validated['bedrooms_min'])) {
            $query->where('bedrooms', '>=', $validated['bedrooms_min']);
        }
        if (isset($validated['parking_min'])) {
            $query->where('parking_spaces', '>=', $validated['parking_min']);
        }
        foreach ([
            'delivery_status', 'building_type', 'cabinet_type', 'heating_type', 'cooling_type',
            'flooring_type', 'renovation_status', 'building_orientation', 'deed_type',
        ] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }
        foreach ([
            'has_storage_room', 'has_elevator', 'has_balcony', 'has_master_bathroom', 'has_loan',
            'is_exchangeable', 'has_pool', 'has_jacuzzi', 'has_sauna', 'has_water',
            'has_electricity', 'has_gas', 'can_aggregate',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $query->where($field, (bool) $validated[$field]);
            }
        }
        if (isset($validated['q'])) {
            $escaped = addcslashes(trim($validated['q']), '%_\\');
            $query->where(fn ($builder) => $builder->where('code', 'like', $escaped.'%')
                ->orWhere('title', 'like', '%'.$escaped.'%')
                ->orWhereHas('owners', fn ($owners) => $owners->where('full_name', 'like', '%'.$escaped.'%')));
        }
        ($validated['order'] ?? 'newest') === 'oldest'
            ? $query->orderBy('created_at')->orderBy('id')
            : $query->orderByDesc('created_at')->orderByDesc('id');

        return PropertyResource::collection($query->paginate($validated['per_page'] ?? 25));
    }

    public function store(StorePropertyRequest $request, CreatePropertyService $service): JsonResponse
    {
        Gate::authorize('create', Property::class);
        /** @var User $user */
        $user = $request->user();

        return (new PropertyResource($service->execute($user, $request->toData())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Property $property): PropertyResource
    {
        Gate::authorize('view', $property);

        return new PropertyResource($property->load(['owners', 'assignedAgent', 'images']));
    }

    public function update(
        UpdatePropertyRequest $request,
        Property $property,
        UpdatePropertyService $service,
    ): PropertyResource {
        Gate::authorize('update', $property);
        /** @var User $user */
        $user = $request->user();

        return new PropertyResource($service->execute($user, $property, $request->toData()));
    }

    public function changeStatus(
        ChangePropertyStatusRequest $request,
        Property $property,
        ChangePropertyStatusService $service,
    ): PropertyResource {
        Gate::authorize('changeStatus', $property);
        /** @var User $user */
        $user = $request->user();

        return new PropertyResource($service->execute($user, $property, $request->toData()));
    }
}

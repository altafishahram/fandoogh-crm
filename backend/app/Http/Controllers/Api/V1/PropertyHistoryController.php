<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Property\PropertyHistoryResource;
use App\Models\Property;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class PropertyHistoryController extends Controller
{
    public function index(Property $property): AnonymousResourceCollection
    {
        Gate::authorize('view', $property);

        return PropertyHistoryResource::collection(
            $property->histories()->orderByDesc('occurred_at')->orderByDesc('id')->paginate(50),
        );
    }
}

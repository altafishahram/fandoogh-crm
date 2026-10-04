<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Matching\Services\RelatedMatchQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MatchNotification\MatchNotificationResource;
use App\Models\Customer;
use App\Models\MatchNotification;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class RelatedMatchController extends Controller
{
    public function __construct(private readonly RelatedMatchQuery $matches) {}

    public function property(Request $request, Property $property): JsonResponse
    {
        return $this->respond($request, $property);
    }

    public function customer(Request $request, Customer $customer): JsonResponse
    {
        return $this->respond($request, $customer);
    }

    private function respond(Request $request, Property|Customer $record): JsonResponse
    {
        Gate::authorize('view', $record);
        Gate::authorize('viewAny', MatchNotification::class);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        /** @var User $user */
        $user = $request->user();
        $query = $record instanceof Property
            ? $this->matches->forProperty($record, $user)
            : $this->matches->forCustomer($record, $user);
        $unreadCount = $this->matches->unreadCount(clone $query, $user);
        $paginator = $query->with([
            'match.property.images' => static fn ($images) => $images->orderBy('sort_order'),
            'match.customer',
        ])->paginate(10);

        return response()->json([
            'data' => MatchNotificationResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'unread_count' => $unreadCount,
                'request_id' => $request->attributes->get('request_id'),
            ],
        ]);
    }
}

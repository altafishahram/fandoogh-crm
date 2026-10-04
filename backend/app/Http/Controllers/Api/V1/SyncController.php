<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Matching\Services\RelatedMatchQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Customer\CustomerResource;
use App\Http\Resources\Api\V1\Property\PropertyResource;
use App\Models\Customer;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class SyncController extends Controller
{
    public function __construct(private readonly RelatedMatchQuery $matches) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'resource' => ['required', Rule::in(['properties', 'customers'])],
            'after' => ['nullable', 'date'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $resource = (string) $validated['resource'];
        $after = $validated['after'] ?? null;
        $afterId = (int) ($validated['after_id'] ?? 0);
        $limit = (int) ($validated['per_page'] ?? 100);

        if ($resource === 'properties') {
            Gate::authorize('viewAny', Property::class);
            $query = Property::withTrashed()->with('owners');
        } else {
            Gate::authorize('viewAny', Customer::class);
            $query = Customer::withTrashed();
        }

        /** @var Builder<Property|Customer> $query */
        $query->when($after, function (Builder $builder, string $value) use ($afterId): void {
            $builder->where(function (Builder $cursor) use ($value, $afterId): void {
                $cursor->where('updated_at', '>', $value)
                    ->orWhere(fn (Builder $same) => $same->where('updated_at', $value)->where('id', '>', $afterId));
            });
        })->orderBy('updated_at')->orderBy('id');

        $items = $this->matches->withSummaries($query, $user, $resource === 'properties' ? 'property' : 'customer')
            ->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        $items = $items->take($limit)->values();
        $last = $items->last();
        $data = $resource === 'properties'
            ? PropertyResource::collection($items)->resolve($request)
            : CustomerResource::collection($items)->resolve($request);

        return response()->json([
            'data' => $data,
            'meta' => [
                'has_more' => $hasMore,
                'next_after' => $last?->updated_at?->toISOString(),
                'next_after_id' => $last?->getKey(),
            ],
        ]);
    }
}

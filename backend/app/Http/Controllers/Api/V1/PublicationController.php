<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Marketplace\Services\PublicationService;
use App\Domain\User\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyPublication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PublicationController extends Controller
{
    public function show(Request $request, Property $property, PublicationService $service): mixed
    {
        Gate::authorize('view', $property);

        return response()->json(['data' => $this->data($request, $property, $service)]);
    }

    public function update(Request $request, Property $property, PublicationService $service): mixed
    {
        $data = $request->validate(['share_with_agencies' => ['required', 'boolean'], 'publish_public' => ['required', 'boolean'], 'public_title' => ['required', 'string', 'max:200'], 'public_description' => ['nullable', 'string', 'max:5000'], 'image_ids' => ['present', 'array', 'max:5'], 'image_ids.*' => ['integer', 'distinct'], 'responding_user_id' => ['sometimes', 'integer'], 'expected_version' => ['sometimes', 'integer', 'min:0']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $service->save($actor, $property, $data);

        return response()->json(['data' => $this->data($request, $property, $service)]);
    }

    /** @return array<string, mixed> */
    private function data(Request $request, Property $property, PublicationService $service): array
    {
        $listing = PropertyPublication::query()->where('property_id', $property->getKey())->first();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return ['id' => $listing?->getKey(), 'share_with_agencies' => $listing->share_with_agencies ?? false, 'publish_public' => $listing->publish_public ?? false, 'public_title' => $listing->public_title ?? '', 'public_description' => $listing?->public_description, 'image_ids' => $listing?->images->pluck('id')->all() ?? [], 'responding_user_id' => $listing->responding_user_id ?? $property->created_by_user_id, 'version' => $listing->version ?? 0, 'can_publish' => $service->canPublish($actor), 'can_change_advisor' => $actor->roleName() === RoleName::AgencyManager, 'advisors' => User::query()->where('agency_id', $property->agency_id)->where('is_active', true)->whereHas('roles', fn ($query) => $query->whereIn('name', ['agency-manager', 'agent']))->get(['id', 'name']), 'candidate_images' => $property->images->map(fn ($image) => ['id' => $image->getKey(), 'url' => route('api.v1.properties.images.content', ['property' => $property->getKey(), 'image' => $image->getKey()])])->values()];
    }
}

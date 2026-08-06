<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Property\Services\PropertyImageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Property\StorePropertyImageRequest;
use App\Http\Requests\Api\V1\Property\UpdatePropertyImageRequest;
use App\Http\Resources\Api\V1\Property\PropertyImageResource;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class PropertyImageController extends Controller
{
    public function index(Property $property): AnonymousResourceCollection
    {
        Gate::authorize('view', $property);

        return PropertyImageResource::collection($property->images()->orderBy('sort_order')->get());
    }

    public function store(
        StorePropertyImageRequest $request,
        Property $property,
        PropertyImageService $service,
    ): JsonResponse {
        Gate::authorize('manageImages', $property);
        /** @var User $user */
        $user = $request->user();
        $file = $request->file('image');

        return (new PropertyImageResource($service->upload($property, $user, $file)))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdatePropertyImageRequest $request,
        Property $property,
        PropertyImage $image,
        PropertyImageService $service,
    ): PropertyImageResource {
        $this->ensureNested($property, $image);
        Gate::authorize('manageImages', $property);
        /** @var User $user */
        $user = $request->user();

        return new PropertyImageResource($service->update(
            $property,
            $image,
            $user,
            (int) $request->validated('sort_order'),
            (bool) $request->validated('is_cover'),
            CarbonImmutable::parse((string) $request->validated('expected_updated_at')),
        ));
    }

    public function destroy(
        Request $request,
        Property $property,
        PropertyImage $image,
        PropertyImageService $service,
    ): Response {
        $this->ensureNested($property, $image);
        Gate::authorize('manageImages', $property);
        /** @var User $user */
        $user = $request->user();
        $service->delete($property, $image, $user);

        return response()->noContent();
    }

    public function content(Property $property, PropertyImage $image): BinaryFileResponse
    {
        $this->ensureNested($property, $image);
        Gate::authorize('view', $property);

        return response()->file(Storage::disk('local')->path($image->storage_path), [
            'Content-Type' => $image->mime_type,
            'Content-Disposition' => 'inline; filename="image-'.$image->getKey().'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function ensureNested(Property $property, PropertyImage $image): void
    {
        abort_unless($image->property_id === $property->getKey(), Response::HTTP_NOT_FOUND);
    }
}

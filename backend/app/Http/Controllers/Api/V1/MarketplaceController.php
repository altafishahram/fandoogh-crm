<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Marketplace\Services\ListingAccessService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Marketplace\ListingResource;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class MarketplaceController extends Controller
{
    public function index(Request $request, ListingAccessService $access): mixed
    {
        $filters = $request->validate(['audience' => ['sometimes', 'in:agency,public'], 'province_id' => ['sometimes', 'integer'], 'county_id' => ['sometimes', 'integer'], 'city_id' => ['sometimes', 'integer'], 'property_type' => ['sometimes', 'string'], 'transaction_type' => ['sometimes', 'in:sale,rent'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $query = $access->visibleQuery($this->actor($request), $filters['audience'] ?? 'public');
        foreach (['province_id', 'county_id', 'city_id', 'property_type', 'transaction_type'] as $field) {
            if (isset($filters[$field])) {
                $query->whereHas('property', fn ($query) => $query->where($field, $filters[$field]));
            }
        }

        return ListingResource::collection($query->orderByDesc('published_at')->orderByDesc('id')->paginate(20)->withQueryString());
    }

    public function show(Request $request, int $listing, ListingAccessService $access): ListingResource
    {
        return new ListingResource($access->findVisible($listing, $this->actor($request), (string) $request->query('audience', 'public')));
    }

    public function image(Request $request, int $listing, int $image, ListingAccessService $access): mixed
    {
        $publication = $access->findVisible($listing, $this->actor($request), (string) $request->query('audience', 'public'));
        $photo = $publication->images->firstWhere('id', $image);
        abort_unless($photo !== null, 404);
        abort_unless($photo->width * $photo->height <= 25_000_000, 413);
        $originalBytes = Storage::disk('local')->get($photo->storage_path);
        abort_unless(is_string($originalBytes), 404);
        $source = @imagecreatefromstring($originalBytes);
        abort_unless($source !== false, 404);
        // Re-encode pixels only: never send EXIF, original filenames, or source storage paths.
        ob_start();
        imagejpeg($source, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($source);
        abort_unless(is_string($bytes), 404);

        return response($bytes, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'Content-Disposition' => 'inline; filename="listing-image.jpg"']);
    }

    private function actor(Request $request): User|PublicUser
    {
        /** @var User|PublicUser|null $actor */
        $actor = $request->user();
        abort_unless($actor instanceof User || $actor instanceof PublicUser, 401);
        abort_unless($actor->tokenCan($actor instanceof PublicUser ? 'marketplace:public' : 'mobile'), 403);

        return $actor;
    }
}

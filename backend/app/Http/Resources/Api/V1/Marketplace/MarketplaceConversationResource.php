<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Marketplace;

use App\Application\Marketplace\Services\ConversationService;
use App\Models\MarketplaceConversation;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @mixin MarketplaceConversation */
final class MarketplaceConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        abort_unless($actor instanceof User || $actor instanceof PublicUser, 401);
        $service = app(ConversationService::class);
        $open = true;
        try {
            $service->assertOpen($this->resource, $actor);
        } catch (HttpException|ModelNotFoundException) {
            $open = false;
        }

        return [
            'id' => $this->id, 'listing_id' => $this->property_publication_id,
            'audience' => $this->audience, 'title' => $this->publication?->public_title,
            'can_send' => $open, 'unread_count' => $service->unread($this->resource, $actor),
            'last_message' => ($message = $this->messages()->latest('id')->first()) === null ? null : new MarketplaceMessageResource($message),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

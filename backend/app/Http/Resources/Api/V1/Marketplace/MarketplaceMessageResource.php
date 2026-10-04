<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Marketplace;

use App\Application\Marketplace\Services\ConversationService;
use App\Models\MarketplaceMessage;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MarketplaceMessage */
final class MarketplaceMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        return [
            'id' => $this->id, 'conversation_id' => $this->marketplace_conversation_id,
            'client_message_id' => $this->client_message_id, 'body' => $this->body, 'sender_type' => $this->sender_type,
            'is_mine' => ($actor instanceof User || $actor instanceof PublicUser) && $this->sender_type === app(ConversationService::class)->actorType($actor) && (int) $this->sender_id === (int) $actor->getKey(),
            'image_url' => $this->image_path === null ? null : route('api.v1.marketplace.conversations.image', ['conversation' => $this->marketplace_conversation_id, 'message' => $this->id]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

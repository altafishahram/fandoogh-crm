<?php

declare(strict_types=1);

namespace App\Application\Marketplace\Services;

use App\Domain\User\Enums\RoleName;
use App\Models\MarketplaceConversation;
use App\Models\MarketplaceMessage;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ConversationService
{
    public function __construct(private readonly ListingAccessService $listings) {}

    public function actorType(User|PublicUser $actor): string
    {
        return $actor instanceof PublicUser ? 'public_user' : 'user';
    }

    /** @return Builder<MarketplaceConversation> */
    public function visible(User|PublicUser $actor): Builder
    {
        $this->assertActive($actor);

        return MarketplaceConversation::query()->where(function (Builder $query) use ($actor): void {
            $query->where(function (Builder $requester) use ($actor): void {
                $requester->where('requester_type', $this->actorType($actor))->where('requester_id', $actor->getKey());
            });
            if ($actor instanceof User) {
                $manager = $actor->roleName() === RoleName::AgencyManager;
                if ($manager) {
                    $query->orWhere('requester_agency_id', $actor->agency_id);
                }
                $query->orWhere(function (Builder $publisher) use ($actor, $manager): void {
                    $publisher->where('publisher_agency_id', $actor->agency_id);
                    if (! $manager) {
                        $publisher->whereHas('publication', fn (Builder $publication) => $publication->where('responding_user_id', $actor->getKey()));
                    }
                });
            }
        });
    }

    public function find(User|PublicUser $actor, int $id): MarketplaceConversation
    {
        return $this->visible($actor)->findOrFail($id);
    }

    public function start(User|PublicUser $actor, int $listing, string $audience): MarketplaceConversation
    {
        $this->assertActive($actor);
        $publication = $this->listings->findVisible($listing, $actor, $audience);
        abort_if($actor instanceof User && (int) $publication->agency_id === (int) $actor->agency_id, 422, 'امکان شروع گفتگو با آژانس خودتان وجود ندارد.');

        return MarketplaceConversation::query()->firstOrCreate([
            'property_publication_id' => $publication->getKey(),
            'audience' => $audience,
            'requester_type' => $this->actorType($actor),
            'requester_id' => $actor->getKey(),
        ], [
            'publisher_agency_id' => $publication->agency_id,
            'requester_agency_id' => $actor instanceof User ? $actor->agency_id : null,
        ]);
    }

    public function assertOpen(MarketplaceConversation $conversation, User|PublicUser $actor): void
    {
        // The publisher may not qualify for the requester's public audience. Test visibility
        // using the original requester; participant access is independently checked first.
        $requester = $conversation->requester_type === 'public_user'
            ? PublicUser::query()->findOrFail($conversation->requester_id)
            : User::query()->findOrFail($conversation->requester_id);
        $this->listings->findVisible((int) $conversation->property_publication_id, $requester, $conversation->audience);
    }

    public function send(MarketplaceConversation $conversation, User|PublicUser $actor, ?string $body, ?UploadedFile $image, ?string $clientMessageId = null): MarketplaceMessage
    {
        $this->find($actor, (int) $conversation->getKey());
        $this->assertOpen($conversation, $actor);
        if ($clientMessageId !== null) {
            $existing = $conversation->messages()->where('sender_type', $this->actorType($actor))->where('sender_id', $actor->getKey())->where('client_message_id', $clientMessageId)->first();
            if ($existing !== null) {
                return $existing;
            }
        }
        $path = $image === null ? null : $this->storeImage($image);
        try {
            return $conversation->messages()->create([
                'sender_type' => $this->actorType($actor), 'sender_id' => $actor->getKey(),
                'client_message_id' => $clientMessageId, 'body' => $body, 'image_path' => $path, 'image_mime' => $image === null ? null : 'image/jpeg',
            ]);
        } catch (\Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            if ($clientMessageId !== null && $exception instanceof UniqueConstraintViolationException) {
                return $conversation->messages()->where('sender_type', $this->actorType($actor))->where('sender_id', $actor->getKey())->where('client_message_id', $clientMessageId)->firstOrFail();
            }
            throw $exception;
        }
    }

    public function markRead(MarketplaceConversation $conversation, User|PublicUser $actor, ?int $through): int
    {
        $this->find($actor, (int) $conversation->getKey());
        $latest = (int) ($conversation->messages()->max('id') ?? 0);
        $through ??= $latest;
        abort_if($through !== 0 && ! $conversation->messages()->whereKey($through)->exists(), 422);
        $keys = ['marketplace_conversation_id' => $conversation->getKey(), 'actor_type' => $this->actorType($actor), 'actor_id' => $actor->getKey()];
        DB::table('marketplace_conversation_reads')->insertOrIgnore([...$keys, 'last_read_message_id' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_conversation_reads')->where($keys)->where('last_read_message_id', '<', $through)->update(['last_read_message_id' => $through, 'updated_at' => now()]);

        return (int) DB::table('marketplace_conversation_reads')->where($keys)->value('last_read_message_id');
    }

    public function unread(MarketplaceConversation $conversation, User|PublicUser $actor): int
    {
        $last = (int) DB::table('marketplace_conversation_reads')->where('marketplace_conversation_id', $conversation->getKey())->where('actor_type', $this->actorType($actor))->where('actor_id', $actor->getKey())->value('last_read_message_id');

        return $conversation->messages()->where('id', '>', $last)->where(fn (Builder $query) => $query->where('sender_type', '!=', $this->actorType($actor))->orWhere('sender_id', '!=', $actor->getKey()))->count();
    }

    private function storeImage(UploadedFile $image): string
    {
        $dimensions = @getimagesize($image->getPathname());
        if ($dimensions === false || $dimensions[0] * $dimensions[1] > 25000000) {
            throw ValidationException::withMessages(['image' => ['ابعاد تصویر بیش از حد مجاز است.']]);
        }
        $bytes = file_get_contents($image->getPathname());
        $source = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($source === false) {
            abort(415);
        }
        try {
            ob_start();
            imagejpeg($source, null, 90);
            $clean = ob_get_clean();
            if (! is_string($clean)) {
                abort(415);
            }
            $path = 'marketplace/chat/'.Str::uuid().'.jpg';
            if (! Storage::disk('local')->put($path, $clean)) {
                abort(503);
            }

            return $path;
        } finally {
            imagedestroy($source);
        }
    }

    private function assertActive(User|PublicUser $actor): void
    {
        abort_unless($actor->is_active, 403);
        if ($actor instanceof User) {
            abort_if($actor->trashed(), 403);
        }
        if ($actor instanceof User) {
            $agency = $actor->agency;
            abort_unless($actor->agency_id !== null && $agency !== null && $agency->is_active && $agency->is_verified, 403);
            abort_if($actor->must_change_password, 403);
        }
    }
}

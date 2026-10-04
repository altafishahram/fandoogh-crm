<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Marketplace\Services\ConversationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Marketplace\StoreMarketplaceMessageRequest;
use App\Http\Resources\Api\V1\Marketplace\MarketplaceConversationResource;
use App\Http\Resources\Api\V1\Marketplace\MarketplaceMessageResource;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class MarketplaceConversationController extends Controller
{
    public function __construct(private readonly ConversationService $service) {}

    public function index(Request $request): mixed
    {
        return MarketplaceConversationResource::collection($this->service->visible($this->actor($request))->with('publication')->latest('updated_at')->paginate(25));
    }

    public function store(Request $request, int $listing): mixed
    {
        $data = $request->validate(['audience' => ['required', Rule::in(['public', 'agency'])]]);

        return (new MarketplaceConversationResource($this->service->start($this->actor($request), $listing, $data['audience'])))->response()->setStatusCode(201);
    }

    public function messages(Request $request, int $conversation): mixed
    {
        $data = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']]);
        $query = $this->service->find($this->actor($request), $conversation)->messages();
        if (isset($data['after_id'])) {
            $query->where('id', '>', $data['after_id']);
        }

        return MarketplaceMessageResource::collection($query->orderBy('id')->paginate(50));
    }

    public function send(StoreMarketplaceMessageRequest $request, int $conversation): mixed
    {
        $actor = $this->actor($request);
        $record = $this->service->find($actor, $conversation);
        $image = $request->file('image');
        abort_unless($image === null || $image instanceof UploadedFile, 422);
        $message = $this->service->send($record, $actor, $request->validated('body'), $image, $request->validated('client_message_id'));
        $record->touch();

        return (new MarketplaceMessageResource($message))->response()->setStatusCode(201);
    }

    public function image(Request $request, int $conversation, int $message): mixed
    {
        $actor = $this->actor($request);
        $record = $this->service->find($actor, $conversation);
        $this->service->assertOpen($record, $actor);
        $attachment = $record->messages()->findOrFail($message);
        abort_unless(is_string($attachment->image_path) && Storage::disk('local')->exists($attachment->image_path), 404);

        return response()->file(Storage::disk('local')->path($attachment->image_path), [
            'Content-Type' => $attachment->image_mime, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'inline; filename="chat-image-'.$attachment->id.'"',
        ]);
    }

    public function read(Request $request, int $conversation): mixed
    {
        $data = $request->validate(['through_message_id' => ['nullable', 'integer', 'min:0']]);
        $actor = $this->actor($request);
        $record = $this->service->find($actor, $conversation);

        return response()->json(['data' => ['last_read_message_id' => $this->service->markRead($record, $actor, isset($data['through_message_id']) ? (int) $data['through_message_id'] : null)]]);
    }

    private function actor(Request $request): User|PublicUser
    {
        /** @var User|PublicUser|null $actor */
        $actor = $request->user();
        abort_unless($actor instanceof User || $actor instanceof PublicUser, 401);

        return $actor;
    }
}

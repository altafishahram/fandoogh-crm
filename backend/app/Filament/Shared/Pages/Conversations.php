<?php

declare(strict_types=1);

namespace App\Filament\Shared\Pages;

use App\Application\Marketplace\Services\ConversationService;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class Conversations extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'گفتگوهای بازار';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected string $view = 'filament.marketplace.conversations';

    #[Url(as: 'conversation')]
    public ?int $selectedConversation = null;

    public string $body = '';

    public mixed $image = null;

    public int $messageLimit = 50;

    public static function canAccess(): bool
    {
        return Marketplace::canAccess();
    }

    public function selectConversation(int $id): void
    {
        $service = app(ConversationService::class);
        $record = $service->find($this->actor(), $id);
        $this->selectedConversation = $id;
        $this->messageLimit = 50;
        $service->markRead($record, $this->actor(), null);
    }

    public function loadEarlier(): void
    {
        $this->messageLimit = min(500, $this->messageLimit + 50);
    }

    public function send(): void
    {
        $this->validate(['body' => ['nullable', 'string', 'max:5000', 'required_without:image'], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'required_without:body']]);
        abort_unless($this->selectedConversation !== null, 422);
        $service = app(ConversationService::class);
        $record = $service->find($this->actor(), $this->selectedConversation);
        abort_unless($this->image === null || $this->image instanceof UploadedFile, 422);
        $service->send($record, $this->actor(), $this->body === '' ? null : $this->body, $this->image);
        $record->touch();
        $service->markRead($record, $this->actor(), null);
        $this->body = '';
        $this->image = null;
    }

    public function getViewData(): array
    {
        $service = app(ConversationService::class);
        $actor = $this->actor();
        $conversations = $service->visible($actor)->with('publication')->latest('updated_at')->limit(100)->get();
        $selected = $this->selectedConversation === null ? null : $service->find($actor, $this->selectedConversation);
        $messages = $selected?->messages()->latest('id')->limit($this->messageLimit)->get()->reverse();
        $open = false;
        if ($selected !== null) {
            try {
                $service->assertOpen($selected, $actor);
                $open = true;
            } catch (HttpException|ModelNotFoundException) {
            }
            $through = $messages?->last()?->id;
            if ($through !== null) {
                $service->markRead($selected, $actor, (int) $through);
            }
        }

        return compact('conversations', 'selected', 'messages', 'open', 'service', 'actor');
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}

<x-filament-panels::page>
    <div dir="rtl" wire:poll.15s class="melkban-marketplace-chat grid gap-6 lg:grid-cols-3">
        <aside class="melkban-chat-sidebar space-y-3" aria-label="فهرست گفتگوها">
            @forelse($conversations as $conversation)
                <button type="button" aria-pressed="{{ $selectedConversation === $conversation->id ? 'true' : 'false' }}" wire:key="conversation-{{ $conversation->id }}" wire:click="selectConversation({{ $conversation->id }})" class="w-full rounded-xl border p-4 text-right {{ $selectedConversation === $conversation->id ? 'ring-2 ring-primary-500' : '' }}">
                    <span class="block font-bold">{{ $conversation->publication?->public_title }}</span>
                    <span class="block text-sm">{{ $conversation->audience === 'public' ? 'مخاطب عمومی' : 'همکاری آژانس' }} · {{ $service->unread($conversation, $actor) }} پیام نخوانده</span>
                </button>
            @empty<p>هنوز گفتگویی شروع نشده است.</p>@endforelse
        </aside>
        <section class="melkban-chat-thread space-y-4 lg:col-span-2" aria-label="پیام‌های گفتگو">
            @if($selected)
                <h2 class="text-lg font-bold">{{ $selected->publication?->public_title }}</h2>
                @if(!$open)<p class="rounded-lg bg-gray-100 p-4 dark:bg-gray-800">این آگهی اکنون فعال نیست. سابقه گفتگو محفوظ است؛ ارسال پیام و دریافت تصویر تا فعال‌شدن آگهی متوقف است.</p>@endif
                <x-filament::button color="gray" wire:click="loadEarlier">نمایش پیام‌های قدیمی‌تر</x-filament::button>
                <div class="melkban-chat-messages max-h-[32rem] space-y-3 overflow-y-auto" aria-live="polite">
                    @foreach($messages ?? [] as $message)
                        <article wire:key="message-{{ $message->id }}" class="melkban-chat-message rounded-lg border p-4 space-y-2">
                            <p class="text-sm font-bold">{{ $message->sender_type === 'user' && (int)$message->sender_id === (int)$actor->id ? 'شما' : 'طرف گفتگو' }}</p>
                            @if($message->body)<p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>@endif
                            @if($message->image_path && $open)<a href="{{ route('marketplace.web.chat-image', ['conversation'=>$selected->id, 'message'=>$message->id]) }}" target="_blank" rel="noopener"><img src="{{ route('marketplace.web.chat-image', ['conversation'=>$selected->id, 'message'=>$message->id]) }}" alt="تصویر ارسالی در گفتگو" loading="lazy" class="max-h-60 rounded-lg" /></a>@endif
                            <time class="text-xs text-gray-500">{{ \App\Filament\Shared\Support\PersianDate::format($message->created_at) }}</time>
                        </article>
                    @endforeach
                </div>
                @if($open)
                    <form wire:submit="send" class="melkban-chat-form space-y-4">
                        <label class="block space-y-2"><span>متن پیام</span><textarea wire:model="body" rows="3" maxlength="5000" class="w-full rounded-lg border-gray-300 dark:bg-gray-900"></textarea></label>
                        @error('body')<p class="text-danger-600" role="alert">{{ $message }}</p>@enderror
                        <label class="block space-y-2"><span>تصویر (حداکثر ۱۰ مگابایت)</span><input type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" /></label>
                        @error('image')<p class="text-danger-600" role="alert">{{ $message }}</p>@enderror
                        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="send,image">ارسال پیام</x-filament::button>
                        <span wire:loading wire:target="send,image" role="status">در حال ارسال…</span>
                    </form>
                @endif
            @else<p>برای مشاهده پیام‌ها یک گفتگو را انتخاب کنید.</p>@endif
        </section>
    </div>
</x-filament-panels::page>

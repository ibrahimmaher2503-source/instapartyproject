<div>
    @if ($historyUnavailable)
        <p role="alert" class="rounded border border-red-300 p-3">{{ __('communication::chat.history_unavailable') }}</p>
        <button type="button" wire:click="refreshMessages" class="mt-2 text-primary-600 underline">{{ __('chat_moderation.refresh_history') }}</button>
    @endif

    @if ($messages === [] && ! $historyUnavailable)
        <p>{{ __('chat_moderation.no_messages') }}</p>
    @else
        <ol class="space-y-3">
            @foreach ($messages as $message)
                <li class="rounded border p-3">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <strong>{{ $message['sender'] }}</strong>
                        @php($timezone = auth()->guard('web')->user()?->timezone ?? 'Africa/Cairo')
                        <time datetime="{{ $message['created_at'] }}">{{ \Illuminate\Support\Carbon::parse($message['created_at'])->timezone($timezone)->translatedFormat('d M Y, H:i') }} <span>({{ $timezone }})</span></time>
                    </div>
                    <p dir="auto" class="whitespace-pre-wrap break-words">{{ $message['redacted'] ? __('chat_moderation.message_redacted') : ($message['body'] ?? '') }}</p>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($historyPageToken !== null)
        <button type="button" wire:click="loadEarlierMessages" class="mt-3 text-primary-600 underline">{{ __('communication::chat.load_earlier') }}</button>
    @endif
</div>

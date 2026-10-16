<div x-data x-init="$el.scrollTop = $el.scrollHeight">
    @forelse ($this->messages as $message)
        @php
            $isFirestoreMessage = is_array($message);
            $isSelf = $isFirestoreMessage ? $message['senderUserId'] === (string) auth()->id() : $message->sender_id === auth()->id();
            $isFlagged = $isFirestoreMessage ? $message['blocked'] : $message->flagged;
            $isRedacted = $isFirestoreMessage ? $message['redacted'] : $message->redacted;
            $messageBody = $isFirestoreMessage ? $message['body'] : $message->body;
            $messageTime = $isFirestoreMessage ? \Illuminate\Support\Carbon::parse($message['createdAt']) : $message->created_at;
        @endphp

        <x-filament::section
            :icon="$isSelf ? 'heroicon-o-arrow-right-circle' : 'heroicon-o-arrow-left-circle'"
            :icon-color="$isFlagged ? 'danger' : ($isSelf ? 'primary' : 'gray')"
        >
            <x-slot name="heading">
                {{ $isSelf ? __('communication::chat.you') : ($this->threadPublicId !== '' ? __('communication::chat.vendor') : __('communication::chat.customer')) }}
            </x-slot>

            <x-slot name="description">
                {{ $messageTime->copy()->timezone(auth()->user()->timezone ?? 'Africa/Cairo')->format('Y-m-d H:i') }}
            </x-slot>

            @if ($isRedacted)
                <x-filament::badge color="warning">
                    {{ __('communication::chat.message_redacted') }}
                </x-filament::badge>
            @elseif ($isFlagged)
                <x-filament::badge color="danger">
                    {{ __('communication::chat.message_blocked') }}
                </x-filament::badge>
            @else
                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                {{ $messageBody ?? __('communication::chat.body_unavailable') }}
                </p>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            {{ __('communication::chat.empty') }}
        </x-filament::section>
    @endforelse
</div>

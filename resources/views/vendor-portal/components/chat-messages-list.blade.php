<div x-data x-init="$el.scrollTop = $el.scrollHeight">
    @forelse ($this->messages as $message)
        @php $isSelf = $message->sender_id === auth()->id(); @endphp

        <x-filament::section
            :icon="$isSelf ? 'heroicon-o-arrow-right-circle' : 'heroicon-o-arrow-left-circle'"
            :icon-color="$message->flagged ? 'danger' : ($isSelf ? 'primary' : 'gray')"
        >
            <x-slot name="heading">
                {{ $isSelf ? __('communication::chat.you') : __('communication::chat.customer') }}
            </x-slot>

            <x-slot name="description">
                {{ $message->created_at->format('H:i') }}
            </x-slot>

            @if ($message->redacted)
                <x-filament::badge color="warning">
                    {{ __('communication::chat.message_redacted') }}
                </x-filament::badge>
            @elseif ($message->flagged)
                <x-filament::badge color="danger">
                    {{ __('communication::chat.message_blocked') }}
                </x-filament::badge>
            @else
                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                    {{ $message->body ?? __('communication::chat.body_unavailable') }}
                </p>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            {{ __('communication::chat.empty') }}
        </x-filament::section>
    @endforelse
</div>

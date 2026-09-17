<x-filament::section
    icon="heroicon-o-chat-bubble-left-right"
    :heading="__('communication::chat.panel_title')"
>
    @if ($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::Placeholder)
        <p>{{ __('communication::chat.placeholder_notice') }}</p>

    @elseif ($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::Frozen)
        <x-filament::section
            icon="heroicon-o-exclamation-triangle"
            icon-color="warning"
            :heading="__('communication::chat.frozen_banner_title')"
        >
            <p>{{ __('communication::chat.frozen_banner_body') }}</p>

            @if ($this->thread?->frozenByUser)
                <p>{{ __('communication::chat.frozen_by', ['name' => $this->thread->frozenByUser->name]) }}</p>
            @endif
        </x-filament::section>

        @include('vendor-portal.components.chat-messages-list')

    @elseif ($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::Closed)
        @include('vendor-portal.components.chat-messages-list')

        <p>{{ __('communication::chat.closed_notice') }}</p>

    @elseif ($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::SystemLocked)
        <p>{{ __('communication::chat.system_locked_notice') }}</p>

    @else
        @include('vendor-portal.components.chat-messages-list')

        @if ($flashMessage)
            <x-filament::badge :color="$isBlocked ? 'danger' : 'success'">
                {{ $flashMessage }}
            </x-filament::badge>
        @endif

        <form wire:submit="sendMessage">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model="body"
                    :placeholder="__('communication::chat.compose_placeholder')"
                />
            </x-filament::input.wrapper>

            <x-filament::button
                type="submit"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>{{ __('communication::chat.send_button') }}</span>
                <span wire:loading>{{ __('communication::chat.sending') }}</span>
            </x-filament::button>
        </form>
    @endif
</x-filament::section>

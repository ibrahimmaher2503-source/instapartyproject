<section aria-label="{{ __('communication::chat.panel_title') }}">
    @if ($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::Frozen)
        <p class="mb-3 rounded border border-amber-300 p-3">{{ __('communication::chat.frozen_banner_body') }}</p>
    @elseif ($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::Closed)
        <p class="mb-3 rounded border p-3">{{ __('communication::chat.closed_notice') }}</p>
    @elseif ($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::SystemLocked)
        <p class="mb-3 rounded border p-3">{{ __('communication::chat.system_locked_notice') }}</p>
    @endif

    @include('vendor-portal.components.chat-messages-list')

    @if ($historyUnavailable)
        <p role="alert" class="my-3 rounded border border-red-300 p-3">{{ __('communication::chat.history_unavailable') }}</p>
    @endif
    <div class="my-3 flex gap-3">
        <button type="button" class="sf-text-link" wire:click="refreshMessages">{{ __('communication::chat.refresh') }}</button>
        @if ($historyPageToken)
            <button type="button" class="sf-text-link" wire:click="loadEarlierMessages">{{ __('communication::chat.load_earlier') }}</button>
        @endif
    </div>

    @if ($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::Open)
        @if ($flashMessage)
            <p role="status" class="my-3 {{ $isBlocked ? 'text-red-700' : 'text-green-700' }}">{{ $flashMessage }}</p>
        @endif
        <form wire:submit="sendMessage" class="flex gap-2">
            <label class="sr-only" for="booking-chat-message">{{ __('communication::chat.compose_placeholder') }}</label>
            <input id="booking-chat-message" type="text" wire:model="body" maxlength="2000" class="min-w-0 flex-1 rounded border px-3 py-2" placeholder="{{ __('communication::chat.compose_placeholder') }}">
            <button type="submit" wire:loading.attr="disabled" class="sf-button-primary">{{ __('communication::chat.send_button') }}</button>
        </form>
    @endif
</section>

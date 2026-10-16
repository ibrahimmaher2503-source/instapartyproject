@if ($historyUnavailable)
    <x-filament::badge color="danger">{{ __('communication::chat.history_unavailable') }}</x-filament::badge>
@endif
<div class="mt-3 flex gap-3">
    <x-filament::button type="button" wire:click="refreshMessages" color="gray">
        {{ __('communication::chat.refresh') }}
    </x-filament::button>
    @if ($historyPageToken)
        <x-filament::button type="button" wire:click="loadEarlierMessages" color="gray">
            {{ __('communication::chat.load_earlier') }}
        </x-filament::button>
    @endif
</div>

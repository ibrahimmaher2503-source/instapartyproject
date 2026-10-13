<x-filament-panels::page>
    <x-filament-panels::form wire:submit="verifyPhone">
        {{ $this->form }}

        @if (! $this->isVerified)
            <x-filament::button type="button" wire:click="sendCode" wire:loading.attr="disabled" wire:target="sendCode">
                {{ __('vendor-portal.phone_verification.send_code') }}
            </x-filament::button>
            <x-filament-panels::form.actions
                :actions="[
                    \Filament\Actions\Action::make('verifyPhone')
                        ->label(__('vendor-portal.phone_verification.verify'))
                        ->submit('verifyPhone'),
                ]"
            />
        @endif
    </x-filament-panels::form>
</x-filament-panels::page>

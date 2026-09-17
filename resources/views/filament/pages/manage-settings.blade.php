<x-filament-panels::page>

    <x-filament-panels::form wire:submit="saveSettings">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="[
                \Filament\Actions\Action::make('save')
                    ->label('Save Settings')
                    ->submit('saveSettings'),
            ]"
        />
    </x-filament-panels::form>

    <x-filament::section>
        {{ $this->table }}
    </x-filament::section>

</x-filament-panels::page>

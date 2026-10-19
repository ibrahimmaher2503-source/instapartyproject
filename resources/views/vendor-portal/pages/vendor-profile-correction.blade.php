<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        {{ __('identity::vendor_correction.description') }}
    </p>

    @if ($this->hasUnsupportedFields())
        <div role="alert" class="rounded-lg bg-warning-50 p-4 text-sm text-warning-800 dark:bg-warning-400/10 dark:text-warning-300">
            {{ __('identity::vendor_correction.unsupported') }}
        </div>
    @endif

    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        @if ($this->canSubmitCorrections())
            <x-filament-panels::form.actions
                :actions="[
                    \Filament\Actions\Action::make('save')
                        ->label(__('identity::vendor_correction.submit'))
                        ->submit('save'),
                ]"
            />
        @endif
    </x-filament-panels::form>
</x-filament-panels::page>

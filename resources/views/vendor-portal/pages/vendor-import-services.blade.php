<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="import">
            {{ $this->form }}

            <x-filament::button type="submit">
                {{ __('catalog.import_button') }}
            </x-filament::button>
        </form>
    </x-filament::section>

    @if ($this->lastImport)
        @if ($this->lastImport->status === 'completed')
            <x-filament::section
                icon="heroicon-o-check-circle"
                icon-color="success"
                :heading="__('catalog.imported_rows', ['count' => $this->lastImport->imported_rows])"
            />
        @else
            <x-filament::section
                icon="heroicon-o-x-circle"
                icon-color="danger"
                :heading="__('catalog.import_failed_rows', ['count' => $this->lastImport->error_rows])"
            >
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('catalog.row') }}</th>
                            <th>{{ __('catalog.field') }}</th>
                            <th>{{ __('catalog.error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->lastImport->errors as $error)
                            <tr>
                                <td>{{ $error->row_number }}</td>
                                <td>{{ $error->field }}</td>
                                <td>{{ $error->message[app()->getLocale()] ?? $error->message['en'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>

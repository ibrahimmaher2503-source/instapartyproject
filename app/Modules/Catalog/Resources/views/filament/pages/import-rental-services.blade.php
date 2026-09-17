<x-filament-panels::page>
    <div class="ip-import-layout">
        <x-filament::section
            icon="heroicon-o-document-arrow-up"
            :heading="__('catalog.import_upload_heading')"
            :description="__('catalog.import_upload_description')"
        >
            <form class="ip-import-form" wire:submit="import">
                {{ $this->form }}

                <div class="ip-import-actions">
                    <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray" wire:loading.attr="disabled" wire:target="import">
                        <span wire:loading.remove wire:target="import">{{ __('catalog.import_button') }}</span>
                        <span wire:loading wire:target="import">{{ __('catalog.importing') }}</span>
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        color="gray"
                        icon="heroicon-o-clock"
                        :href="\App\Modules\Catalog\Filament\Resources\ExcelImportResource::getUrl('index')"
                    >
                        {{ __('catalog.import_history') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @if ($this->lastImport)
            <x-filament::section
                class="ip-import-results"
                :icon="$this->lastImport->status === 'completed' ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle'"
                :icon-color="$this->lastImport->status === 'completed' ? 'success' : 'danger'"
                :heading="$this->lastImport->status === 'completed'
                    ? __('catalog.imported_rows', ['count' => $this->lastImport->imported_rows])
                    : __('catalog.import_failed_rows', ['count' => $this->lastImport->error_rows])"
            >
                <div class="ip-import-summary">
                    <x-filament::badge color="success">
                        {{ __('catalog.imported_rows_count') }}: {{ $this->lastImport->imported_rows }}
                    </x-filament::badge>
                    <x-filament::badge :color="$this->lastImport->error_rows > 0 ? 'danger' : 'gray'">
                        {{ __('catalog.error_rows') }}: {{ $this->lastImport->error_rows }}
                    </x-filament::badge>
                </div>

                @if ($this->lastImport->error_rows > 0)
                    <div class="overflow-x-auto">
                        <table class="ip-import-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('catalog.row') }}</th>
                                    <th scope="col">{{ __('catalog.field') }}</th>
                                    <th scope="col">{{ __('catalog.error') }}</th>
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
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>

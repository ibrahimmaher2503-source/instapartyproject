@if ($isImpersonating)
    <x-filament-widgets::widget>
        <x-filament::section icon="heroicon-o-shield-exclamation" icon-color="danger">
            <x-slot name="heading">
                {{ __('identity.vendor_portal.impersonation_banner') }}
            </x-slot>

            <x-slot name="description">
                {{ $adminName }}@if ($startedAt) · {{ $startedAt }}@endif
            </x-slot>

            <x-slot name="headerEnd">
                <x-filament::button
                    color="danger"
                    size="sm"
                    wire:click="endImpersonation"
                    wire:loading.attr="disabled"
                >
                    {{ __('identity.vendor_portal.impersonation_end') }}
                </x-filament::button>
            </x-slot>
        </x-filament::section>
    </x-filament-widgets::widget>
@endif

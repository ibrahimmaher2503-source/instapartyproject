<x-filament-panels::page>
    <x-filament::section :heading="__('tax.filter_heading', [], app()->getLocale()) ?: __('tax.apply_filter')">
        <form wire:submit.prevent="apply">
            {{ $this->form }}

            <x-filament::button type="submit">
                {{ __('tax.apply_filter') }}
            </x-filament::button>
        </form>
    </x-filament::section>

    @php $stats = $this->getStats(); @endphp

    <x-filament::section :heading="__('tax.total_vat_collected')">
        <p>{{ number_format($stats['total_vat'] / 100, 2) }} EGP</p>
    </x-filament::section>

    <x-filament::section :heading="__('tax.taxable_bookings')">
        <p>{{ number_format($stats['total_bookings']) }}</p>
    </x-filament::section>

    <x-filament::section :heading="__('tax.avg_vat_per_booking')">
        <p>{{ number_format($stats['avg_vat'] / 100, 2) }} EGP</p>
    </x-filament::section>
</x-filament-panels::page>

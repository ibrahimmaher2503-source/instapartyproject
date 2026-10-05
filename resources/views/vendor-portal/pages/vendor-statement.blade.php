<x-filament-panels::page>
    <div class="mb-4">
        {!! $this->printButton() !!}
    </div>
    <div class="overflow-x-auto">
        {{ $this->table }}
    </div>
    <style>
        @media print {
            .fi-sidebar, .fi-sidebar-close-overlay, .fi-topbar, button { display: none !important; }
            .fi-main { margin: 0 !important; }
            table { font-size: 11px; width: 100%; }
        }
    </style>
</x-filament-panels::page>

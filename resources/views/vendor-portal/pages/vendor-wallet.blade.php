<x-filament-panels::page>
    <x-filament::section>
        {{ $this->walletInfolist }}
    </x-filament::section>

    <div class="overflow-x-auto -mx-4 sm:mx-0">
        {{ $this->table }}
    </div>

    {{-- Sticky withdrawal CTA for mobile viewports --}}
    <div class="fixed bottom-0 inset-x-0 p-4 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 shadow-xl md:hidden z-50">
        {{ $this->requestWithdrawalAction }}
    </div>
</x-filament-panels::page>

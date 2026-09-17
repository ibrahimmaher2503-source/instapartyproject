<x-filament-panels::page>
    {{-- Status tab bar --}}
    <div class="flex flex-wrap gap-2 mb-4">
        @php
            $tabs = [
                'pending'     => __('vendor-portal.bookings.tab_pending'),
                'accepted'    => __('vendor-portal.bookings.tab_accepted'),
                'in_progress' => __('vendor-portal.bookings.tab_in_progress'),
                'completed'   => __('vendor-portal.bookings.tab_completed'),
                'rejected'    => __('vendor-portal.bookings.tab_rejected'),
            ];
        @endphp

        @foreach ($tabs as $key => $label)
            <button
                wire:click="$set('statusFilter', '{{ $key }}')"
                @class([
                    'px-4 py-2 rounded-full text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500',
                    'bg-primary-600 text-white shadow'        => $this->statusFilter === $key,
                    'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $this->statusFilter !== $key,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="overflow-x-auto -mx-4 sm:mx-0">
        {{ $this->table }}
    </div>
</x-filament-panels::page>

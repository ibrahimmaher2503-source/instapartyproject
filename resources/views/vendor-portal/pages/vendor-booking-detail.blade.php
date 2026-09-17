<x-filament-panels::page>
    {{ $this->infolist }}

    @livewire(
        'communication.vendor.restricted-chat-panel',
        ['bookingPublicId' => $this->bookingPublicId],
        key('chat-' . $this->bookingPublicId)
    )
</x-filament-panels::page>

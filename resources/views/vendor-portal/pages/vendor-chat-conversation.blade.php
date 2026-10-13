<x-filament-panels::page>
    @livewire('communication.vendor.restricted-chat-panel', ['bookingPublicId' => $this->bookingPublicId], key('chat-'.$this->bookingPublicId))
</x-filament-panels::page>

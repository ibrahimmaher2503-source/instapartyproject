<x-filament-panels::page>
    <x-filament::tabs>
        @foreach ($this->tabs() as $key => $label)
            <x-filament::tabs.item
                :active="$activeTab === $key"
                wire:click="setTab('{{ $key }}')"
            >
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>

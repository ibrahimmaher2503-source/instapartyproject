<x-filament-panels::page>
    <x-filament::section :heading="__('identity.compliance.required_documents')">
        <ul class="space-y-2">
            @foreach ($requiredDocuments as $document)
                <li>{{ $document['type'] }}: {{ $document['status'] }}@if ($document['expires_at']) · {{ __('identity.fields.expires_at') }}: {{ $document['expires_at'] }}@endif</li>
            @endforeach
        </ul>
    </x-filament::section>
    {{ $this->table }}
</x-filament-panels::page>

<x-filament-panels::page>
    <x-filament::section :heading="__('identity::security.title')">
        <p>{{ $enabled ? __('identity::security.enabled') : __('identity::security.setup_hint') }}</p>
        <p>{{ $required ? __('identity::security.required') : __('identity::security.optional') }}</p>
        @if ($secret)
            <div>{!! $qr !!}</div>
            <p>{{ __('identity::security.manual_key') }}: <code dir="ltr">{{ $secret }}</code></p>
            <p>{{ __('identity::security.confirm_hint') }}</p>
        @endif
        @if ($recoveryCodes !== [])
            <p>{{ __('identity::security.recovery_hint') }}</p>
            <ul dir="ltr">@foreach ($recoveryCodes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>

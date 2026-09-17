<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach (['firebase' => __('communication.provider_settings.firebase.heading'), 'sms_misr' => __('communication.provider_settings.sms.heading')] as $provider => $heading)
            <x-filament::section :heading="$heading">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <dt>{{ __('communication.provider_settings.status_enabled') }}</dt>
                    <dd>{{ ($this->providerStatus[$provider]['enabled'] ?? false) ? __('communication.provider_settings.yes') : __('communication.provider_settings.no') }}</dd>
                    <dt>{{ __('communication.provider_settings.status_configured') }}</dt>
                    <dd>{{ ($this->providerStatus[$provider]['configured'] ?? false) ? __('communication.provider_settings.yes') : __('communication.provider_settings.no') }}</dd>
                    <dt>{{ __('communication.provider_settings.status_connection') }}</dt>
                    <dd>{{ __('communication.provider_settings.status.'.($this->providerStatus[$provider]['status'] ?? 'not_checked')) }}</dd>
                    <dt>{{ __('communication.provider_settings.status_last_checked') }}</dt>
                    <dd>{{ $this->providerStatus[$provider]['checked_at'] ?? __('communication.provider_settings.never') }}</dd>
                    @if ($provider === 'sms_misr')
                        <dt>{{ __('communication.provider_settings.sms.balance') }}</dt>
                        <dd>{{ $this->smsBalance ?? __('communication.provider_settings.not_available') }}</dd>
                    @endif
                </dl>
            </x-filament::section>
        @endforeach
    </div>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-o-check">{{ __('communication.provider_settings.save') }}</x-filament::button>
            <x-filament::button type="button" color="gray" icon="heroicon-o-signal" wire:click="testFirebase">{{ __('communication.provider_settings.test_firebase') }}</x-filament::button>
            <x-filament::button type="button" color="gray" icon="heroicon-o-banknotes" wire:click="checkSmsBalance">{{ __('communication.provider_settings.sms.check_balance') }}</x-filament::button>
            <x-filament::button type="button" color="gray" icon="heroicon-o-chat-bubble-left-ellipsis" wire:click="testSms">{{ __('communication.provider_settings.test_sms') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

<x-filament-panels::page>
    <x-filament::section :heading="__('communication::setup.title')">
        <p>{{ __('communication::setup.readiness_hint') }}</p>
        <ul>
            @foreach ($setupChecklist as $item)
                <li>{{ __('communication::setup.' . $item['label']) }}:
                    {{ $item['configured'] ? __('communication::setup.settings_present') : __('communication::setup.settings_missing') }}
                </li>
            @endforeach
        </ul>
        <p>{{ __('communication::setup.queue_hint') }}</p>
        <p>{{ __('communication::setup.acceptance_hint') }}</p>
    </x-filament::section>
    @foreach ($cards as $card)
        @php
            $statusColor = match ($card['statusBadge']) {
                'healthy'     => 'success',
                'degraded'    => 'danger',
                'unreachable' => 'danger',
                default       => 'gray',
            };
        @endphp

        <x-filament::section :heading="$card['channelLabel']">
            <x-slot name="headerEnd">
                <x-filament::badge :color="$statusColor">
                    {{ __('communication.provider_health.status.' . $card['statusBadge']) }}
                </x-filament::badge>
            </x-slot>

            <dl>
                <div>
                    <dt>{{ __('communication.provider_health.card.sent_24h') }}</dt>
                    <dd>{{ number_format($card['sentLast24h']) }}</dd>
                </div>
                <div>
                    <dt>{{ __('communication.provider_health.card.failed_24h') }}</dt>
                    <dd>{{ number_format($card['failedLast24h']) }}</dd>
                </div>
                <div>
                    <dt>{{ __('communication.provider_health.card.adapter') }}</dt>
                    <dd>{{ $card['providerName'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('communication.provider_health.card.configuration') }}</dt>
                    <dd>{{ $card['configured']
                        ? __('communication.provider_health.status.configured')
                        : __('communication.provider_health.status.not_configured') }}</dd>
                </div>

                @if ($card['isReachable'] && $card['latencyMs'] !== null)
                    <div>
                        <dt>{{ __('communication.provider_health.card.latency') }}</dt>
                        <dd>{{ $card['latencyMs'] }}ms</dd>
                    </div>
                @endif

                @if ($card['lastSuccessAt'])
                    <div>
                        <dt>{{ __('communication.provider_health.card.last_success') }}</dt>
                        <dd>{{ \Illuminate\Support\Carbon::parse($card['lastSuccessAt'])->diffForHumans() }}</dd>
                    </div>
                @endif

                @if ($card['lastFailureAt'])
                    <div>
                        <dt>{{ __('communication.provider_health.card.last_failure') }}</dt>
                        <dd>{{ \Illuminate\Support\Carbon::parse($card['lastFailureAt'])->diffForHumans() }}</dd>
                    </div>
                @endif

                @if ($card['note'])
                    <div>
                        <dt>{{ __('communication.provider_health.card.note') }}</dt>
                        <dd>{{ $card['note'] }}</dd>
                    </div>
                @endif
            </dl>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>

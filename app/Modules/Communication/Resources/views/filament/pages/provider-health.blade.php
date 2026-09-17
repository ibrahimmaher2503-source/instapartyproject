<x-filament-panels::page>
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
                        <dd>{{ $card['lastSuccessAt']?->diffForHumans() }}</dd>
                    </div>
                @endif

                @if ($card['lastFailureAt'])
                    <div>
                        <dt>{{ __('communication.provider_health.card.last_failure') }}</dt>
                        <dd>{{ $card['lastFailureAt']?->diffForHumans() }}</dd>
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

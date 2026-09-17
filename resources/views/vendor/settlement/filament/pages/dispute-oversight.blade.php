<x-filament-panels::page>
    @php $stats = $this->getStats(); @endphp

    <x-filament::section :heading="__('settlement.pending_refunds')">
        <p>{{ number_format($stats['pending_refunds']) }}</p>
    </x-filament::section>

    <x-filament::section :heading="__('settlement.pending_withdrawals')">
        <p>{{ number_format($stats['pending_withdrawals']) }}</p>
    </x-filament::section>

    <x-filament::section :heading="__('settlement.total_disputed_amount')">
        <p><bdi dir="ltr">{{ $this->formatMoney($stats['total_disputed'], $stats['total_disputed_currency']) }}</bdi></p>
    </x-filament::section>

    <x-filament::section :heading="__('settlement.pending_refunds_section')">
        <table>
            <thead>
                <tr>
                    <th>{{ __('settlement.ref') }}</th>
                    <th>{{ __('settlement.status') }}</th>
                    <th>{{ __('settlement.amount') }}</th>
                    <th>{{ __('settlement.reason') }}</th>
                    <th>{{ __('settlement.created_at') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getPendingRefunds() as $row)
                    <tr>
                        <td><bdi dir="ltr">{{ $row->gateway_ref }}</bdi></td>
                        <td>
                            <x-filament::badge :color="$row->status === 'pending' ? 'warning' : 'info'">
                                {{ $this->refundStatusLabel($row->status) }}
                            </x-filament::badge>
                        </td>
                        <td><bdi dir="ltr">{{ $this->formatMoney($row->amount_minor, $row->amount_currency) }}</bdi></td>
                        <td>
                            <span>{{ $this->refundReasonLabel($row->reason_code) }}</span>
                            @if ($note = $this->localizedText($row->reason_notes))
                                <span class="block text-xs">{{ $note }}</span>
                            @endif
                        </td>
                        <td>{{ $this->formatDate($row->created_at) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">—</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section :heading="__('settlement.pending_withdrawals_section')">
        <table>
            <thead>
                <tr>
                    <th>{{ __('settlement.withdrawal_id') }}</th>
                    <th>{{ __('settlement.owner') }}</th>
                    <th>{{ __('settlement.amount') }}</th>
                    <th>{{ __('settlement.created_at') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getPendingWithdrawals() as $row)
                    <tr>
                        <td><bdi dir="ltr">{{ $row->public_id }}</bdi></td>
                        <td>{{ $this->localizedText($row->business_name, __('settlement.owner_unknown')) }}</td>
                        <td><bdi dir="ltr">{{ $this->formatMoney($row->requested_amount_minor, $row->requested_amount_currency) }}</bdi></td>
                        <td>{{ $this->formatDate($row->created_at) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">—</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>

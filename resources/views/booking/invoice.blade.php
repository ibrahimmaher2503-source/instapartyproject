<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('booking.invoice.title') }} — {{ $booking->reference_no }}</title>
    <style>
        body { color: #1f2937; font-family: Arial, sans-serif; margin: 2rem auto; max-width: 900px; padding: 0 1rem; }
        header { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 2rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: .65rem .4rem; text-align: start; }
        .totals { margin-top: 1.5rem; margin-inline-start: auto; max-width: 420px; }
        .total { font-size: 1.2rem; font-weight: 700; }
        .print { margin-bottom: 1rem; }
        @media print { .print { display: none; } body { margin: 0; max-width: none; } }
    </style>
</head>
<body>
    <button class="print" type="button" onclick="window.print()">{{ __('booking.invoice.print') }}</button>
    <header>
        <div>
            @if (filled(config('app.name')) && config('app.name') !== 'Laravel')
                <div><strong>{{ config('app.name') }}</strong></div>
            @endif
            <h1>{{ __('booking.invoice.title') }}</h1>
            <div>{{ __('booking.invoice.document_reference') }}: <bdi>{{ $booking->reference_no }}</bdi></div>
            <div>{{ __('booking.invoice.customer') }}: {{ $booking->customer?->name ?? '—' }}</div>
            @if (filled($booking->invoice_name) || filled($booking->invoice_tax_id))
                @if (filled($booking->invoice_name))
                    <div>{{ __('booking.invoice.invoice_name') }}: {{ $booking->invoice_name }}</div>
                @endif
                @if (filled($booking->invoice_tax_id))
                    <div>{{ __('booking.invoice.invoice_tax_id') }}: <bdi>{{ $booking->invoice_tax_id }}</bdi></div>
                @endif
            @else
                <div>{{ __('booking.invoice.no_tax_details_recorded') }}</div>
            @endif
        </div>
        <div>
            <div>{{ __('booking.invoice.date') }}: {{ $booking->created_at?->translatedFormat('d M Y') }}</div>
            <div>{{ __('booking.invoice.currency') }}: {{ $booking->total_currency }}</div>
        </div>
    </header>

    <table>
        <thead><tr><th>{{ __('booking.invoice.item') }}</th><th>{{ __('booking.invoice.amount') }}</th></tr></thead>
        <tbody>
        @foreach ($booking->vendors as $vendor)
            <tr>
                <td colspan="2">{{ __('booking.invoice.vendor') }}: {{ $vendor->vendor?->getTranslation('business_name', app()->getLocale(), useFallbackLocale: true) ?? '—' }}</td>
            </tr>
            @foreach ($vendor->items as $item)
                <tr>
                    <td>{{ is_array($item->name_snapshot) ? ($item->name_snapshot[app()->getLocale()] ?? $item->name_snapshot['en'] ?? '—') : '—' }} × {{ $item->quantity }}</td>
                    <td>{{ $format((int) $item->line_total_minor, $item->line_total_currency) }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr><td>{{ __('booking.invoice.item_subtotal') }}</td><td>{{ $format((int) $itemSubtotalMinor, $booking->subtotal_currency) }}</td></tr>
        @if ($subtotalReconciliationMinor !== 0)
            <tr><td>{{ __('booking.invoice.recorded_subtotal_adjustment') }}</td><td>{{ $format((int) $subtotalReconciliationMinor, $booking->subtotal_currency) }}</td></tr>
        @endif
        <tr><td>{{ __('booking.columns.services_subtotal') }}</td><td>{{ $format((int) $booking->subtotal_minor, $booking->subtotal_currency) }}</td></tr>
        @if ((int) $booking->discount_total_minor > 0)
            <tr><td>{{ __('booking.invoice.discount') }}</td><td>−{{ $format((int) $booking->discount_total_minor, $booking->discount_total_currency) }}</td></tr>
        @endif
        @if ((int) $booking->loyalty_redeemed_minor > 0)
            <tr><td>{{ __('booking.invoice.loyalty_redemption') }}</td><td>−{{ $format((int) $booking->loyalty_redeemed_minor, $booking->loyalty_redeemed_currency) }}</td></tr>
        @endif
        <tr><td>{{ __('booking.invoice.vendor_delivery') }}</td><td>{{ $format((int) $vendorDeliveryMinor, $booking->delivery_total_currency) }}</td></tr>
        <tr><td>{{ __('booking.columns.transport_base') }}</td><td>{{ $format((int) ($booking->transport_base_minor ?? 0), $booking->transport_base_currency ?: $booking->total_currency) }}</td></tr>
        @foreach ($adjustments as $adjustment)
            <tr><td>{{ __('booking.columns.transportation_adjustment') }} — {{ $adjustment->source }}</td><td>{{ $format((int) $adjustment->amount_minor, $adjustment->currency) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div>{{ __('booking.columns.delivery_total') }}: {{ $format((int) $booking->delivery_total_minor, $booking->delivery_total_currency) }}</div>
        <div class="total">{{ __('booking.columns.total') }}: {{ $format((int) $booking->total_minor, $booking->total_currency) }}</div>
        <div>{{ __('booking.columns.amount_paid') }}: {{ $format($paymentTotals['net_paid_minor'], $booking->total_currency) }}</div>
        <div>{{ __('booking.invoice.recorded_vat') }}: {{ $format((int) $booking->total_vat_minor, $booking->total_currency) }}</div>
        <p>{{ __('booking.invoice.tax_context') }}</p>
    </div>

    <section>
        <h2>{{ __('booking.invoice.payment_status') }}: {{ __('booking::booking.payment_status.'.$booking->payment_status->getValue()) }}</h2>
        @forelse ($booking->payments as $payment)
            <div>
                <bdi>{{ $payment->public_id }}</bdi>
                · {{ __('payments::payments.status.'.$payment->status->getValue()) }}
                @if ($payment->method !== null)
                    · {{ $payment->method->getLabel() }}
                @endif
                · {{ $format((int) $payment->amount_minor, $payment->amount_currency) }}
            </div>
        @empty
            <div>—</div>
        @endforelse
    </section>
</body>
</html>

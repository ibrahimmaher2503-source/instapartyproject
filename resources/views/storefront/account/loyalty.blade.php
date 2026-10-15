@extends('storefront.account.layout')
@section('account-title', __('account.loyalty'))
@section('account-content')
    @forelse ($customerBalances as $balance)
        <article class="sf-account-panel mb-4">
            <h2 class="font-bold">{{ data_get($balance['vendor_name'], app()->getLocale(), data_get($balance['vendor_name'], 'en', '')) }}</h2>
            <p>{{ __('storefront.me.loyalty.total_card.points', ['points' => $balance['balance_points']]) }}</p>
        </article>
    @empty
        <p class="sf-account-empty">{{ __('loyalty.account.no_balances') }}</p>
    @endforelse
    <section class="sf-account-panel mt-6" aria-labelledby="loyalty-history-heading">
        <h2 id="loyalty-history-heading" class="font-bold">{{ __('loyalty.account.history') }}</h2>
        @forelse ($history as $entry)
            <article class="sf-account-row border-t border-ink-100 py-3">
                <div>
                    <strong>{{ $entry->vendorProfile?->getTranslation('business_name', app()->getLocale(), useFallbackLocale: true) ?? '' }}</strong>
                    <p>{{ $entry->getTranslation('reason', app()->getLocale(), useFallbackLocale: true) }}</p>
                    <span class="text-sm text-ink-500">{{ __('loyalty.account.direction.'.$entry->direction->value) }} · {{ $entry->created_at->translatedFormat('d F Y') }}</span>
                </div>
                <bdi dir="ltr">{{ $entry->direction->value === 'earn' ? '+' : '−' }}{{ $entry->points }} {{ __('loyalty.account.points') }}</bdi>
            </article>
        @empty
            <p class="mt-3 text-sm text-ink-500">{{ __('loyalty.account.no_history') }}</p>
        @endforelse
        {{ $history->links() }}
    </section>
@endsection

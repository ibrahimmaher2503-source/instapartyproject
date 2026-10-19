@extends('storefront.account.layout')
@section('account-title', __('account.loyalty'))
@section('account-content')
    @forelse ($balances as $balance)
        <article class="sf-account-panel mb-4">
            <h2 class="font-bold">{{ data_get($balance['vendor_name'], app()->getLocale(), data_get($balance['vendor_name'], 'en', '')) }}</h2>
            <p>{{ __('storefront.me.loyalty.total_card.points', ['points' => $balance['balance_points']]) }}</p>
        </article>
    @empty
        <p class="sf-account-empty">{{ __('storefront.loyalty.empty') }}</p>
    @endforelse
@endsection

@extends('storefront.account.layout')
@section('account-title', __('storefront.addresses.title'))
@section('account-content')
    @forelse ($addresses as $address)
        <article class="sf-account-panel mb-4">
            <h2 class="font-bold">{{ $address->label }} @if ($address->is_default)<span class="text-sm">· {{ __('storefront.addresses.default') }}</span>@endif</h2>
            <p>{{ $address->address_line }}@if ($address->city) · {{ $address->city->getTranslation('name', app()->getLocale()) }}@endif</p>
            <p>{{ $address->recipient_name }} · <bdi dir="ltr">{{ $address->recipient_phone_e164 }}</bdi></p>
        </article>
    @empty
        <p class="sf-account-empty">{{ __('storefront.addresses.empty') }}</p>
    @endforelse
    {{ $addresses->links() }}
@endsection

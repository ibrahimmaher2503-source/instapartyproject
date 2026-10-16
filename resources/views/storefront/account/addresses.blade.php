@extends('storefront.account.layout')
@section('account-title', __('storefront.me.addresses.title'))
@section('account-content')
    <form class="sf-account-panel sf-account-form mb-6" method="POST" action="{{ route('storefront.account.addresses.store') }}">
        @csrf
        <h2 class="mb-4 font-bold">{{ __('storefront.me.addresses.add') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <label>{{ __('storefront.me.addresses.label') }}<input class="sf-field mt-1 w-full" name="label" maxlength="60" value="{{ old('label') }}" required></label>
            <label>{{ __('booking.columns.city') }}
                <select class="sf-field mt-1 w-full" name="city_id" required>
                    <option value="">—</option>
                    @foreach (($cities ?? collect()) as $city)
                        <option value="{{ $city->public_id }}" @selected(old('city_id') === $city->public_id)>{{ $city->getTranslation('name', app()->getLocale()) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="sm:col-span-2">{{ __('booking.columns.address_line') }}<input class="sf-field mt-1 w-full" name="address_line" maxlength="255" value="{{ old('address_line') }}" required></label>
            <label>{{ __('booking.columns.building') }}<input class="sf-field mt-1 w-full" name="building" maxlength="50" value="{{ old('building') }}"></label>
            <label>{{ __('booking.columns.floor') }}<input class="sf-field mt-1 w-full" name="floor" maxlength="20" value="{{ old('floor') }}"></label>
            <label>{{ __('booking.columns.apartment') }}<input class="sf-field mt-1 w-full" name="apartment" maxlength="20" value="{{ old('apartment') }}"></label>
            <label>{{ __('booking.columns.landmark') }}<input class="sf-field mt-1 w-full" name="landmark" maxlength="255" value="{{ old('landmark') }}"></label>
            <label>{{ __('booking.columns.recipient_name') }}<input class="sf-field mt-1 w-full" name="recipient_name" maxlength="120" value="{{ old('recipient_name') }}" required></label>
            <label>{{ __('booking.columns.recipient_phone') }}<input class="sf-field mt-1 w-full" type="tel" dir="ltr" name="recipient_phone_e164" maxlength="20" value="{{ old('recipient_phone_e164') }}" required></label>
        </div>
        <label class="sf-account-check mt-4 block"><input type="checkbox" name="is_default" value="1" @checked(old('is_default'))> {{ __('storefront.me.addresses.default') }}</label>
        <button class="sf-button sf-button--primary mt-4" type="submit">{{ __('storefront.me.addresses.save') }}</button>
    </form>
    @forelse ($addresses as $address)
        <article class="sf-account-panel mb-4">
            <h2 class="font-bold">{{ $address->label }} @if ($address->is_default)<span class="text-sm">· {{ __('storefront.me.addresses.default') }}</span>@endif</h2>
            <p>{{ $address->address_line }}@if ($address->city) · {{ $address->city->getTranslation('name', app()->getLocale()) }}@endif</p>
            <p>{{ $address->recipient_name }} · <bdi dir="ltr">{{ $address->recipient_phone_e164 }}</bdi></p>
            <details class="mt-4">
                <summary class="sf-text-link cursor-pointer">{{ __('storefront.me.addresses.edit') }}</summary>
                <form class="sf-account-form mt-4" method="POST" action="{{ route('storefront.account.addresses.update', ['addressPublicId' => $address->public_id]) }}">
                    @csrf
                    @method('PATCH')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label>{{ __('storefront.me.addresses.label') }}<input class="sf-field mt-1 w-full" name="label" maxlength="60" value="{{ $address->label }}" required></label>
                        <label>{{ __('booking.columns.city') }}
                            <select class="sf-field mt-1 w-full" name="city_id" required>
                                @foreach (($cities ?? collect()) as $city)
                                    <option value="{{ $city->public_id }}" @selected($address->city?->public_id === $city->public_id)>{{ $city->getTranslation('name', app()->getLocale()) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="sm:col-span-2">{{ __('booking.columns.address_line') }}<input class="sf-field mt-1 w-full" name="address_line" maxlength="255" value="{{ $address->address_line }}" required></label>
                        @foreach (['building' => 50, 'floor' => 20, 'apartment' => 20, 'landmark' => 255] as $field => $maxLength)
                            <label>{{ __('booking.columns.'.$field) }}<input class="sf-field mt-1 w-full" name="{{ $field }}" maxlength="{{ $maxLength }}" value="{{ $address->{$field} }}"></label>
                        @endforeach
                        <label>{{ __('booking.columns.recipient_name') }}<input class="sf-field mt-1 w-full" name="recipient_name" maxlength="120" value="{{ $address->recipient_name }}" required></label>
                        <label>{{ __('booking.columns.recipient_phone') }}<input class="sf-field mt-1 w-full" type="tel" dir="ltr" name="recipient_phone_e164" maxlength="20" value="{{ $address->recipient_phone_e164 }}" required></label>
                    </div>
                    <button class="sf-button sf-button--secondary mt-4" type="submit">{{ __('storefront.me.addresses.update') }}</button>
                </form>
            </details>
            @unless ($address->is_default)
                <form class="mt-3" method="POST" action="{{ route('storefront.account.addresses.default', ['addressPublicId' => $address->public_id]) }}">
                    @csrf
                    <button class="sf-button sf-button--quiet" type="submit">{{ __('storefront.me.addresses.default') }}</button>
                </form>
            @endunless
            <form class="mt-3" method="POST" action="{{ route('storefront.account.addresses.destroy', ['addressPublicId' => $address->public_id]) }}">
                @csrf
                @method('DELETE')
                <button class="sf-button sf-button--quiet" type="submit">{{ __('storefront.me.addresses.remove') }}</button>
            </form>
        </article>
    @empty
        <p class="sf-account-empty">{{ __('storefront.me.addresses.empty') }}</p>
    @endforelse
    {{ $addresses->links() }}
@endsection

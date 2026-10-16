@extends('storefront.account.layout')

@section('account-title', __('communication::chat.panel_title'))

@section('account-content')
    <section class="sf-account-panel" aria-labelledby="booking-chat-heading">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="booking-chat-heading" class="text-xl font-semibold">{{ __('communication::chat.panel_title') }}</h2>
                <p class="mt-1 text-sm text-ink-600">{{ __('communication::chat.account.booking_reference') }}: <bdi>{{ $bookingReference }}</bdi></p>
            </div>
            <a class="sf-text-link" href="{{ route('storefront.account.chats', ['locale' => $locale]) }}">{{ __('communication::chat.account.back_to_conversations') }}</a>
        </div>
        @livewire('communication.vendor.restricted-chat-panel', ['threadPublicId' => $threadPublicId])
    </section>
@endsection

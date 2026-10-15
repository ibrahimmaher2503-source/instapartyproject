@extends('storefront.account.layout')

@section('account-title', __('account.chats'))

@section('account-content')
    <section aria-labelledby="inquiry-conversations-heading">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="inquiry-conversations-heading" class="text-xl font-semibold">{{ __('communication::chat.account.inquiries_heading') }}</h2>
                <p class="mt-1 text-sm text-ink-600">{{ __('communication::chat.account.inquiries_description') }}</p>
            </div>
            <a class="sf-text-link" href="{{ route('storefront.account.inquiries.index', ['locale' => $locale]) }}">{{ __('communication::chat.account.all_inquiries') }}</a>
        </div>

        <div class="grid gap-3">
            @forelse ($inquiries as $inquiry)
                <a href="{{ route('storefront.inquiries.show', ['locale' => $locale, 'inquiryPublicId' => $inquiry->public_id]) }}" class="sf-account-panel sf-account-row block transition hover:border-primary-300">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3>{{ $inquiry->serviceNameFor($locale) }}</h3>
                            <p class="mt-1 text-sm text-ink-500">{{ $inquiry->vendorNameFor($locale) }}</p>
                        </div>
                        <time class="text-sm text-ink-500" datetime="{{ $inquiry->updated_at->toIso8601String() }}">{{ $inquiry->updated_at->timezone(auth()->user()->timezone ?? 'Africa/Cairo')->format('Y-m-d H:i') }}</time>
                    </div>
                    <p class="mt-3 text-sm text-ink-600">{{ __('communication::communication.vendor_inquiries.message_count', ['count' => $inquiry->messages_count]) }}</p>
                    <span class="sr-only">{{ __('communication::chat.account.open_inquiry') }}</span>
                </a>
            @empty
                <div class="sf-account-empty">
                    <p>{{ __('communication::communication.vendor_inquiries.empty_title') }}</p>
                    <p>{{ __('communication::communication.vendor_inquiries.customer_empty_description') }}</p>
                    <a class="sf-text-link mt-3 inline-flex" href="{{ route('storefront.search') }}">{{ __('storefront.nav.search') }}</a>
                </div>
            @endforelse
        </div>
        {{ $inquiries->links() }}
    </section>

    <section class="mt-8" aria-labelledby="booking-conversations-heading">
        <div class="mb-4">
            <h2 id="booking-conversations-heading" class="text-xl font-semibold">{{ __('communication::chat.account.booking_chats_heading') }}</h2>
            <p class="mt-1 text-sm text-ink-600">{{ __('communication::chat.account.booking_chats_description') }}</p>
        </div>

        @if ($bookingChatConfigured)
            <div class="grid gap-3">
                @forelse ($bookingChats as $thread)
                    @php
                        $bookingReference = $bookingsById[$thread->booking_id] ?? null;
                    @endphp
                    @if ($bookingReference)
                        @php
                            $vendorName = $thread->vendorProfile?->getTranslation('business_name', $locale, useFallbackLocale: true) ?? __('communication::chat.account.vendor_unavailable');
                            $threadState = $thread->frozen_at !== null
                                ? 'locked'
                                : (in_array($thread->status, ['open', 'locked', 'closed'], true) ? $thread->status : 'locked');
                        @endphp
                        <article class="sf-account-panel sf-account-row">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3>{{ $vendorName }}</h3>
                                    <p class="mt-1">{{ __('communication::chat.account.booking_reference') }}: <bdi>{{ $bookingReference->referenceNumber }}</bdi></p>
                                    @if ($bookingReference->eventStartsAt)
                                        <p class="text-sm text-ink-500">{{ $bookingReference->eventStartsAt->timezone(auth()->user()->timezone ?? 'Africa/Cairo')->translatedFormat('j M Y H:i') }}</p>
                                    @endif
                                </div>
                                <span class="rounded-full border px-3 py-1 text-sm">{{ __('communication::chat.thread_status.'.$threadState) }}</span>
                            </div>
                            <a class="sf-text-link mt-3 inline-flex" href="{{ route('storefront.account.bookings.show', ['locale' => $locale, 'publicId' => $bookingReference->publicId]) }}">{{ __('account.view_details') }}</a>
                        </article>
                    @endif
                @empty
                    <div class="sf-account-empty"><p>{{ __('communication::chat.no_threads') }}</p></div>
                @endforelse
            </div>
            {{ $bookingChats->links() }}
        @else
            <div class="sf-account-empty"><p>{{ __('communication::chat.account.booking_chat_unavailable') }}</p></div>
        @endif
    </section>
@endsection

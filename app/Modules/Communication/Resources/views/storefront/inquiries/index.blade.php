@extends('storefront.account.layout')

@section('account-title', __('communication::communication.vendor_inquiries.my_inquiries'))

@section('account-content')
    <div class="space-y-4">
        @forelse ($inquiries as $inquiry)
            <a href="{{ route('storefront.inquiries.show', ['locale' => $locale, 'inquiryPublicId' => $inquiry->public_id]) }}" class="sf-account-row block transition hover:border-primary-300">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-bold">{{ $inquiry->serviceNameFor($locale) }}</h2>
                        <p class="mt-1 text-sm text-ink-500">{{ $inquiry->vendorNameFor($locale) }}</p>
                    </div>
                    <time class="text-sm text-ink-500" datetime="{{ $inquiry->updated_at->toIso8601String() }}">{{ $inquiry->updated_at->timezone(auth()->user()->timezone ?? 'Africa/Cairo')->format('Y-m-d H:i') }}</time>
                </div>
                <p class="mt-3 text-sm text-ink-600">{{ __('communication::communication.vendor_inquiries.message_count', ['count' => $inquiry->messages_count]) }}</p>
            </a>
        @empty
            <section class="sf-account-panel">
                <h2>{{ __('communication::communication.vendor_inquiries.empty_title') }}</h2>
                <p>{{ __('communication::communication.vendor_inquiries.customer_empty_description') }}</p>
                <a class="sf-text-link mt-4 inline-flex" href="{{ route('storefront.search') }}">{{ __('storefront.nav.search') }}</a>
            </section>
        @endforelse
        {{ $inquiries->links() }}
    </div>
@endsection

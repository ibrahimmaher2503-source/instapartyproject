@extends('storefront.account.layout')

@section('account-title', __('communication::communication.vendor_inquiries.conversation_title'))

@section('account-content')
    <div class="space-y-5">
        <section class="sf-account-panel">
            <a class="sf-text-link" href="{{ route('storefront.services.show', ['locale' => $locale, 'servicePublicId' => $inquiry->service_public_id]) }}">{{ $inquiry->serviceNameFor($locale) }}</a>
            <p class="mt-2 text-sm text-ink-500">{{ $inquiry->vendorNameFor($locale) }}</p>
        </section>

        <section class="space-y-3" aria-label="{{ __('communication::communication.vendor_inquiries.messages') }}">
            @foreach ($inquiry->messages as $message)
                <article class="sf-account-panel">
                    <div class="flex items-center justify-between gap-3 text-sm text-ink-500">
                        <span>{{ __('communication::communication.vendor_inquiries.author.'.($message->author_role === 'customer' ? 'you' : 'vendor')) }}</span>
                        <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone(auth()->user()->timezone ?? 'Africa/Cairo')->format('Y-m-d H:i') }}</time>
                    </div>
                    <p class="mt-3 whitespace-pre-wrap break-words text-ink-900">{{ $message->body }}</p>
                </article>
            @endforeach
        </section>

        <form method="POST" action="{{ route('storefront.inquiries.reply', ['locale' => $locale, 'inquiryPublicId' => $inquiry->public_id]) }}" class="sf-account-panel space-y-3">
            @csrf
            <label for="vendor-inquiry-reply" class="block text-sm font-bold">{{ __('communication::communication.vendor_inquiries.reply_label') }}</label>
            <textarea id="vendor-inquiry-reply" name="body" rows="5" maxlength="5000" required class="sf-field w-full" aria-describedby="vendor-inquiry-reply-help">{{ old('body') }}</textarea>
            @error('body') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <p id="vendor-inquiry-reply-help" class="text-sm text-ink-500">{{ __('communication::communication.vendor_inquiries.message_help') }}</p>
            <button type="submit" class="sf-button sf-button--primary min-h-11 justify-center">{{ __('communication::communication.vendor_inquiries.send_reply') }}</button>
        </form>
    </div>
@endsection

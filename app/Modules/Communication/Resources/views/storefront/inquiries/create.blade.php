@extends('storefront.layouts.app')

@section('title', __('communication::communication.vendor_inquiries.create_title').' · '.__('storefront.common.site_name'))

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        <nav class="mb-6 text-sm text-ink-500" aria-label="{{ __('storefront.common.breadcrumb') }}">
            <a class="sf-text-link" href="{{ route('storefront.services.show', ['locale' => $locale, 'servicePublicId' => $target->servicePublicId]) }}">{{ __('communication::communication.vendor_inquiries.back_to_service') }}</a>
        </nav>

        <section class="rounded-3xl border border-ink-200 bg-white p-6 shadow-surface sm:p-8">
            <p class="text-sm font-bold text-secondary-700">{{ $locale === 'ar' ? $target->vendorNameAr : $target->vendorNameEn }}</p>
            <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-ink-900">{{ __('communication::communication.vendor_inquiries.create_title') }}</h1>
            <p class="mt-3 text-ink-600">{{ __('communication::communication.vendor_inquiries.create_description') }}</p>
            <h2 class="mt-6 rounded-2xl bg-secondary-50 px-4 py-3 text-lg font-bold text-secondary-900">{{ $locale === 'ar' ? $target->serviceNameAr : $target->serviceNameEn }}</h2>

            <form method="POST" action="{{ route('storefront.inquiries.store', ['locale' => $locale, 'servicePublicId' => $target->servicePublicId]) }}" class="mt-7 space-y-4">
                @csrf
                <label for="vendor-inquiry-body" class="block text-sm font-bold text-ink-800">{{ __('communication::communication.vendor_inquiries.message_label') }}</label>
                <textarea id="vendor-inquiry-body" name="body" rows="6" maxlength="5000" required class="sf-field w-full" aria-describedby="vendor-inquiry-help">{{ old('body') }}</textarea>
                @error('body') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                <p id="vendor-inquiry-help" class="text-sm leading-6 text-ink-500">{{ __('communication::communication.vendor_inquiries.message_help') }}</p>
                <button type="submit" class="sf-button sf-button--primary min-h-12 w-full justify-center sm:w-auto">{{ __('communication::communication.vendor_inquiries.send_inquiry') }}</button>
            </form>
        </section>
    </div>
@endsection

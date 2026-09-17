@extends('storefront.layouts.auth')

@section('title', __('storefront.auth.verify.title'))
@section('heading', __('storefront.auth.verify.title'))
@section('subheading', __('storefront.auth.verify.subtitle', ['phone' => "\u{2066}{$phone}\u{2069}"]))

@section('form')
    <form method="POST" action="{{ route('storefront.auth.verify') }}" class="space-y-4" data-auth-form>
        @csrf

        <div class="space-y-1.5">
            <span class="block text-sm font-medium text-ink-900">{{ __('storefront.auth.fields.otp_code') }}</span>
            <div class="sf-otp" data-otp-input>
                @for ($index = 0; $index < 6; $index++)
                    <input type="text" inputmode="numeric" autocomplete="{{ $index === 0 ? 'one-time-code' : 'off' }}" maxlength="1" pattern="[0-9]*" aria-label="{{ __('storefront.auth.fields.otp_code') }} {{ $index + 1 }}" data-otp-digit>
                @endfor
            </div>
            <input type="hidden" name="code" value="{{ old('code') }}" data-otp-value required>
            @error('code')
                <p class="flex items-start gap-1.5 text-xs text-danger"><span>{{ $message }}</span></p>
            @enderror
        </div>

        <x-storefront.button class="w-full" data-submitting="{{ __('storefront.auth.verify.submitting') }}">
            {{ __('storefront.auth.verify.submit') }}
        </x-storefront.button>
    </form>

    <form method="POST" action="{{ route('storefront.auth.verify.send') }}" data-auth-form>
        @csrf
        <button type="submit" class="inline-flex min-h-11 items-center text-sm font-medium text-ink-500 underline underline-offset-2 hover:text-ink-900" data-submitting="{{ __('storefront.auth.verify.resending') }}">
            {{ __('storefront.auth.verify.resend') }}
        </button>
    </form>
@endsection

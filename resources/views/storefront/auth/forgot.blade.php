@extends('storefront.layouts.auth')

@section('title', __('storefront.auth.forgot.title'))
@section('heading', __('storefront.auth.forgot.title'))
@section('subheading', __('storefront.auth.forgot.description'))

@section('form')
    <form method="POST" action="{{ route('storefront.auth.forgot') }}" class="space-y-4" data-auth-form>
        @csrf

        <x-storefront.input
            name="identifier"
            :label="__('storefront.auth.forgot.identifier_label')"
            :placeholder="__('storefront.auth.forgot.identifier_placeholder')"
            required
            autocomplete="username"
            dir="ltr"
        />

        <x-storefront.button class="w-full" data-submitting="{{ __('storefront.auth.forgot.submitting') }}">
            {{ __('storefront.auth.forgot.submit') }}
        </x-storefront.button>
    </form>

    <a href="{{ route('storefront.auth.login') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-ink-500 underline underline-offset-2 hover:text-ink-900">
        {{ __('storefront.auth.forgot.back_to_login') }}
    </a>
@endsection

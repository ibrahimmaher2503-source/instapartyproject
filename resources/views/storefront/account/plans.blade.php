@extends('storefront.account.layout')
@section('account-title', __('account.plans'))
@section('account-content')<div class="sf-account-empty"><p>{{ __('account.plans_unsupported') }}</p><a class="sf-button sf-button--primary" href="{{ route('storefront.wizard') }}">{{ __('account.plan_cta') }}</a></div>@endsection

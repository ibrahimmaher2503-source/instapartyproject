@extends('storefront.layouts.app')

@section('title', __('storefront.home.occasions.view_all').' · '.__('storefront.common.site_name'))

@section('content')
    <div class="sf-home-v2 sf-occasions-index">
        <h1 class="sr-only">{{ __('storefront.home.occasions.view_all') }}</h1>
        @if ($occasions->isEmpty())
            <p class="sf-home-container py-12">{{ __('storefront.home.occasions.empty') }}</p>
        @endif
        <x-home.taxonomy
            :block="['public_id' => 'all-occasions', 'payload' => []]"
            :items="$occasions"
            kind="occasion"
            :show-all="true"
        />
    </div>
@endsection

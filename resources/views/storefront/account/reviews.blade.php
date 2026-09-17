@extends('storefront.account.layout')
@section('account-title', __('account.reviews'))
@section('account-content')@forelse($reviews as $item)<article class="sf-account-panel"><h2>{{ __('account.review_'.$item['type']) }} · {{ $item['review']->rating }}/5</h2><p>{{ $item['review']->body }}</p><time>{{ $item['review']->created_at?->translatedFormat('d F Y') }}</time></article>@empty<div class="sf-account-empty">{{ __('account.no_reviews') }}</div>@endforelse @endsection

@extends('storefront.account.layout')
@section('account-title', __('account.reviews'))
@section('account-content')
    @forelse($reviews as $item)
        <article class="sf-account-panel">
            <h2>{{ __('account.review_'.$item->type->value) }} · {{ $item->rating }}/5</h2>
            <p>{{ __('account.review_target') }}: {{ $item->targetName ?? __('account.review_target_unknown') }}</p>
            <p>{{ $item->body }}</p>
            <p>{{ __('account.review_status') }}: {{ __('reviews::reviews.moderation_status.'.$item->moderationStatus->value) }}</p>
            @if ($item->vendorResponse !== null)
                <section>
                    <h3>{{ __('account.review_vendor_response') }}</h3>
                    <p>{{ $item->vendorResponse }}</p>
                </section>
            @endif
            <time>{{ $item->createdAt?->translatedFormat('d F Y') }}</time>
        </article>
    @empty
        <div class="sf-account-empty">{{ __('account.no_reviews') }}</div>
    @endforelse
@endsection

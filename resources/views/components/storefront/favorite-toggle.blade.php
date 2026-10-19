@props(['service'])

@if (auth()->user()?->hasRole('customer'))
    <form method="POST" action="{{ route('storefront.favorites.store', ['locale' => app()->getLocale(), 'servicePublicId' => $service->public_id]) }}" class="mt-3">
        @csrf
        <button type="submit" class="sf-button sf-button--secondary" aria-label="{{ __('discovery.account.add_favorite') }}">
            <span aria-hidden="true">♡</span>
            {{ __('discovery.account.add_favorite') }}
        </button>
    </form>
@endif

@extends('storefront.account.layout')
@section('account-title', __('account.saved_searches'))
@section('account-content')
    @forelse ($savedSearches as $savedSearch)
        <article class="sf-account-panel mb-4">
            <h2 class="font-bold">{{ $savedSearch->label }}</h2>
            <p class="text-sm text-ink-500">{{ __('discovery.account.saved_on', ['date' => $savedSearch->created_at->translatedFormat('d F Y')]) }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a class="sf-button sf-button--primary" href="{{ route('storefront.search', ['locale' => app()->getLocale(), ...($savedSearch->filters ?? [])]) }}">{{ __('discovery.account.run_search') }}</a>
                <form method="POST" action="{{ route('storefront.account.saved-searches.destroy', ['savedSearch' => $savedSearch->id]) }}">
                    @csrf
                    @method('DELETE')
                    <button class="sf-button sf-button--quiet" type="submit">{{ __('discovery.account.delete_search') }}</button>
                </form>
            </div>
        </article>
    @empty
        <p class="sf-account-empty">{{ __('discovery.account.no_saved_searches') }}</p>
    @endforelse
    {{ $savedSearches->links() }}
@endsection

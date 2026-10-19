@extends('storefront.account.layout')
@section('account-title', __('account.saved_searches'))
@section('account-content')
    @if (session('saved_search_status'))
        <p class="sf-account-panel mb-4 text-sm font-bold text-secondary-700" role="status">{{ session('saved_search_status') }}</p>
    @endif

    @forelse ($savedSearches as $savedSearch)
        <article class="sf-account-panel mb-4">
            <h2 class="font-bold">{{ $savedSearch->label }}</h2>
            <p class="text-sm text-ink-500">{{ $savedSearch->created_at->translatedFormat('d F Y') }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <a class="sf-text-link" href="{{ route('storefront.search', $savedSearch->filters ?? []) }}">{{ __('account.saved_search_run') }}</a>
                <form action="{{ route('storefront.account.saved-searches.destroy', $savedSearch) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button class="text-xs font-bold text-ink-400 underline hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary-300" type="submit">{{ __('account.saved_search_delete') }}</button>
                </form>
            </div>
        </article>
    @empty
        <p class="sf-account-empty">{{ __('account.no_saved_searches') }}</p>
    @endforelse
    {{ $savedSearches->links() }}
@endsection

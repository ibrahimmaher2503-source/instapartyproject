@extends('storefront.account.layout')
@section('account-title', __('account.notifications'))

@section('account-content')
    @forelse($notifications as $notification)
        @php($data = (array) $notification->data)
        @php($localized = (array) (($data['localized_content'] ?? [])[app()->getLocale()] ?? []))
        <article class="sf-account-panel @if (! $notification->read_at) sf-account-panel--unread @endif">
            <h2>{{ $localized['title'] ?? $data['title'] ?? __('account.notification') }}</h2>
            <p>{{ $localized['body'] ?? $data['body'] ?? '' }}</p>
            @if ($bookingUrls[$notification->id] ?? null)
                <a href="{{ $bookingUrls[$notification->id] }}">{{ __('account.view_details') }}</a>
            @endif
            <form method="POST" action="{{ route('storefront.account.notifications.'.($notification->read_at ? 'unread' : 'read'), ['locale' => app()->getLocale(), 'notificationId' => $notification->id]) }}">
                @csrf
                @method('PATCH')
                <button type="submit">{{ __('account.notification_mark_'.($notification->read_at ? 'unread' : 'read')) }}</button>
            </form>
            <time datetime="{{ $notification->created_at?->toISOString() }}">{{ $notification->created_at?->timezone(auth()->user()->timezone ?? 'Africa/Cairo')->translatedFormat(app(App\Modules\Shared\Application\Services\StorefrontText::class)->dateTimeFormat()) }}</time>
        </article>
    @empty
        <div class="sf-account-empty">{{ __('account.no_notifications') }}</div>
    @endforelse
    {{ $notifications->links() }}
@endsection

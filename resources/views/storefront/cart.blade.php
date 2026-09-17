@extends('storefront.layouts.app')

@php
    use Brick\Money\Money;

    $items = $booking?->items ?? collect();
    $locale = app()->getLocale();
    $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
    $totalMinor = (int) ($booking?->total_minor ?? $items->sum('line_total_minor'));
    $currency = (string) ($booking?->total_currency ?? 'EGP');
@endphp

@section('title', __('storefront.cart.title').' · '.__('storefront.common.site_name'))

@section('content')
    <div class="sf-cart-page bg-[var(--sf-ivory)]">
        <section class="border-b border-ink-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
                <p class="text-sm font-bold uppercase tracking-[0.2em] text-secondary-700">{{ __('storefront.nav.your_event') }}</p>
                <h1 class="mt-4 text-4xl font-extrabold tracking-[-0.045em] text-ink-900 sm:text-5xl">{{ __('storefront.cart.title') }}</h1>
            </div>
        </section>

        @if (session('booking_added'))
            <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8"><div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-900" role="status">{{ __('storefront.vendor.add_to_event.added_to_cart') }}</div></div>
        @endif
        @if (session('booking_submitted'))
            <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8"><div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-900" role="status">{{ __('storefront.cart.continue') }}</div></div>
        @endif

        @if (! $booking || $items->isEmpty())
            <section class="mx-auto max-w-3xl px-4 py-12 text-center sm:px-6 lg:px-8 lg:py-16">
                <div class="mx-auto flex size-20 items-center justify-center rounded-full bg-secondary-50 text-3xl text-secondary-700" aria-hidden="true">✦</div>
                <h2 class="mt-7 text-2xl font-extrabold tracking-[-0.03em] text-ink-900">{{ __('storefront.cart.no_items') }}</h2>
                <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-ink-500">{{ __('storefront.your_event.empty_card.body') }}</p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <a href="{{ url('/'.app()->getLocale().'/wizard') }}" class="sf-button sf-button--secondary">{{ __('storefront.cart.start_planning') }} <span aria-hidden="true">↗</span></a>
                    <a href="{{ url('/'.app()->getLocale().'/search') }}" class="sf-button sf-button--quiet">{{ __('storefront.cart.add_items') }}</a>
                </div>
            </section>
        @else
            <section class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:px-8 lg:py-14">
                <div class="space-y-4">
                    @foreach ($booking->vendors as $vendor)
                        <div class="rounded-[1.5rem] border border-ink-200 bg-white p-5 shadow-surface sm:p-6">
                            <div class="flex items-start justify-between gap-4 border-b border-ink-100 pb-4">
                                <div><p class="text-xs font-bold uppercase tracking-[0.14em] text-ink-400">{{ __('storefront.vendor.verified_badge') }}</p><h2 class="mt-1 text-xl font-extrabold text-ink-900">{{ $vendor->vendor ? $storefrontText->translation($vendor->vendor, 'business_name') : '' }}</h2></div>
                                <a href="{{ url('/'.$locale.'/search?vendor='.$vendor->vendor?->public_id) }}" class="text-sm font-bold text-secondary-700">{{ __('storefront.cart.add_more') }}</a>
                            </div>
                            <div class="mt-4 divide-y divide-ink-100">
                                @foreach ($vendor->items as $item)
                                    @php $itemName = $item->name_snapshot[$locale] ?? $item->name_snapshot['en'] ?? ''; @endphp
                                    <div class="flex items-center justify-between gap-4 py-4 first:pt-0 last:pb-0">
                                        <div class="min-w-0"><p class="font-bold text-ink-900">{{ $itemName }}</p><p class="mt-1 text-sm text-ink-500">{{ __('storefront.service.quantity') }}: {{ $item->quantity }}</p></div>
                                        <div class="shrink-0 text-end"><p class="font-extrabold text-secondary-700"><bdi dir="ltr">{{ Money::ofMinor((int) $item->line_total_minor, (string) ($item->line_total_currency ?: $currency))->formatTo($locale) }}</bdi></p><form action="{{ route('storefront.cart.remove', [$booking->public_id, $item->public_id]) }}" method="POST" class="mt-2">@csrf @method('DELETE')<button class="text-xs font-bold text-ink-400 underline hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary-300" type="submit" aria-label="{{ __('storefront.cart.remove_item', ['name' => $itemName]) }}">{{ __('storefront.cart.remove') }}</button></form></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <aside class="h-fit rounded-[1.5rem] bg-secondary-900 p-6 text-white shadow-deep lg:sticky lg:top-24">
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-secondary-200">{{ __('storefront.checkout.steps.summary') }}</p>
                    <dl class="mt-6 space-y-4 border-b border-white/15 pb-6 text-sm"><div class="flex justify-between gap-4"><dt class="text-white/65">{{ __('storefront.cart.subtotal') }}</dt><dd><bdi dir="ltr">{{ Money::ofMinor((int) $items->sum('line_total_minor'), $currency)->formatTo($locale) }}</bdi></dd></div><div class="flex justify-between gap-4"><dt class="text-white/65">{{ __('storefront.cart.delivery') }}</dt><dd><bdi dir="ltr">{{ Money::ofMinor((int) ($booking->delivery_total_minor ?? 0), $currency)->formatTo($locale) }}</bdi></dd></div></dl>
                    <div class="flex justify-between gap-4 pt-5 text-lg font-extrabold"><span>{{ __('storefront.cart.total') }}</span><span><bdi dir="ltr">{{ Money::ofMinor($totalMinor, $currency)->formatTo($locale) }}</bdi></span></div>
                    <form action="{{ route('storefront.cart.submit', $booking->public_id) }}" method="POST" class="mt-7">@csrf<button type="submit" class="sf-button sf-button--light w-full justify-center">{{ __('storefront.checkout.event.submit') }} <span aria-hidden="true">↗</span></button></form>
                    <p class="mt-4 text-center text-xs leading-5 text-white/55">{{ __('storefront.home.trust.secure_sub') }}</p>
                </aside>
            </section>
        @endif
    </div>
@endsection

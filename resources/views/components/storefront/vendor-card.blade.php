@props(['vendor'])

@php
    $text = app(App\Modules\Shared\Application\Services\StorefrontText::class);
    $name = $text->translation($vendor, 'business_name');
    $city = $vendor->primaryCity ? $text->translation($vendor->primaryCity, 'name') : '';
    $bio = $text->translation($vendor, 'bio');
    $cover = $vendor->cover_path ? Illuminate\Support\Facades\Storage::disk('public')->url($vendor->cover_path) : null;
    $logo = $vendor->logo_path ? Illuminate\Support\Facades\Storage::disk('public')->url($vendor->logo_path) : null;
    $initial = mb_substr((string) $name, 0, 1);
@endphp

<article class="group overflow-hidden rounded-[1.25rem] border border-ink-200 bg-white transition hover:-translate-y-0.5 hover:shadow-surface">
    <a href="{{ url('/'.app()->getLocale().'/vendors/'.$vendor->public_id) }}" class="block focus:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-secondary-200" aria-label="{{ __('storefront.vendor.view_profile') }}: {{ $name }}">
        <div class="relative aspect-[16/9] bg-secondary-900">
            @if ($cover)
                <img src="{{ $cover }}" alt="" class="h-full w-full object-cover" loading="lazy" onerror="this.hidden=true">
            @else
                <div class="h-full w-full bg-[radial-gradient(circle_at_75%_25%,rgba(92,190,155,.18),transparent_35%)]"></div>
            @endif
            <span class="absolute inset-inline-start-5 -bottom-7 flex size-14 items-center justify-center overflow-hidden rounded-full border-4 border-white bg-secondary-100 text-xl font-bold text-secondary-800">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="h-full w-full object-cover" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                    <span hidden>{{ $initial }}</span>
                @else
                    {{ $initial }}
                @endif
            </span>
        </div>
        <div class="space-y-3 p-5 pt-10">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="min-w-0 text-lg font-bold text-ink-900">{{ $name }}</h3>
                @if ($vendor->approval_status instanceof \App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState)
                    <x-storefront.badge tone="success">{{ __('storefront.vendor.verified_badge') }}</x-storefront.badge>
                @endif
            </div>
            @if ($city)
                <p class="text-sm text-ink-600">{{ $city }}</p>
            @endif
            <x-storefront.vendor-rating :vendor="$vendor" class="text-accent-600" />
            @if ($bio)
                <p class="line-clamp-2 text-sm leading-6 text-ink-600">{{ $bio }}</p>
            @endif
            <div class="flex items-center justify-between gap-3 border-t border-ink-100 pt-3 text-sm font-semibold">
                <span class="text-ink-600">{{ trans_choice('storefront.vendor.services_count', (int) $vendor->services_count, ['count' => $vendor->services_count]) }}</span>
                <span class="text-secondary-700">{{ __('storefront.vendor.view_profile') }} <span aria-hidden="true">↗</span></span>
            </div>
        </div>
    </a>
</article>

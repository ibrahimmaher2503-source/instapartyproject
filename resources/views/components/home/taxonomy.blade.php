@props(['block', 'items' => [], 'kind'])

@php
    $payload = (array) ($block['payload'] ?? []);
    $requestedIds = collect((array) ($payload['public_ids'] ?? []));
    $items = collect($items)
        ->filter(static fn ($item): bool => $item !== null)
        ->when($requestedIds->isNotEmpty(), function ($items) use ($requestedIds) {
            return $items
                ->filter(static fn ($item): bool => $requestedIds->contains($item->public_id))
                ->sortBy(static fn ($item): int => (int) $requestedIds->search($item->public_id));
        })
        ->values();
    $isOccasion = $kind === 'occasion';
    $prefix = $isOccasion ? 'o' : 'c';
    $eyebrow = $isOccasion ? __('storefront.home.occasions.eyebrow') : __('storefront.home.categories.eyebrow');
    $fallbackTitle = $isOccasion ? __('storefront.home.occasions.heading') : __('storefront.home.categories.heading');
    $fallbackSub = $isOccasion ? __('storefront.home.occasions.sub') : __('storefront.home.categories.sub');
    $browseLabel = $isOccasion ? __('storefront.home.occasions.view_all') : __('storefront.home.categories.view_all');
    $browseUrl = url('/'.app()->getLocale().'/search');
    $storefrontText = app(App\Modules\Shared\Application\Services\StorefrontText::class);
    $fallbacks = collect(config('storefront.service_fallbacks', []));
    $visibleItems = $items->take($isOccasion ? 6 : 8);
@endphp

@if ($items->isNotEmpty())
    <section class="sf-home-section {{ $isOccasion ? 'sf-occasion-section' : 'sf-category-section sf-category-utility' }}" aria-labelledby="taxonomy-heading-{{ $block['public_id'] }}">
        <div class="sf-home-container">
            <x-home.section-header
                :id="'taxonomy-heading-'.$block['public_id']"
                :eyebrow="$eyebrow"
                :title="$payload['title'] ?? $fallbackTitle"
                :description="$fallbackSub"
                :href="$browseUrl"
                :link-label="$browseLabel"
            />

            <ul class="sf-taxonomy-grid {{ $isOccasion ? 'sf-taxonomy-grid--occasions' : 'sf-taxonomy-grid--categories' }}" role="list">
                @foreach ($visibleItems as $index => $item)
                    @php
                        $itemName = $storefrontText->translation($item, 'name') ?: $fallbackTitle;
                        $itemDescription = $storefrontText->translation($item, 'description');
                        $itemUrl = url('/'.app()->getLocale().'/'.$prefix.'/'.$item->code);
                        $iconPath = (string) ($item->icon_path ?? '');
                        $fallbackPath = $fallbacks->isNotEmpty() ? $fallbacks[$index % $fallbacks->count()] : 'images/homepage-scenes/hero.png';
                        $fallback = asset($fallbackPath);
                        $image = blank($iconPath)
                            ? $fallback
                            : (filter_var($iconPath, FILTER_VALIDATE_URL)
                                ? $iconPath
                                : (str_starts_with($iconPath, 'images/')
                                    ? asset($iconPath)
                                    : Illuminate\Support\Facades\Storage::disk('public')->url($iconPath)));
                    @endphp
                    <li class="sf-taxonomy-item">
                        <a class="sf-taxonomy-tile sf-market-card" href="{{ $itemUrl }}">
                            @if ($isOccasion)
                                <img class="sf-taxonomy-photo" src="{{ $image }}" alt="{{ $itemName }}" width="640" height="420" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='{{ $fallback }}';">
                                <span class="sf-taxonomy-shade" aria-hidden="true"></span>
                            @else
                                <span class="sf-category-utility__icon" aria-hidden="true">
                                    <img src="{{ $image }}" alt="" width="56" height="56" loading="lazy" onerror="this.hidden=true;">
                                </span>
                            @endif
                            <span class="sf-taxonomy-name">{{ $itemName }}</span>
                            @if ($isOccasion && filled($itemDescription))
                                <span class="sf-taxonomy-description">{{ $itemDescription }}</span>
                            @endif
                            <span class="sf-taxonomy-arrow" aria-hidden="true">{{ app()->getLocale() === 'ar' ? '←' : '→' }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

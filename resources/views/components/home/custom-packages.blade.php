@props(['packages', 'occasions' => collect()])

@php
    $groups = collect($packages)->groupBy('occasion_code')->map(fn ($group) => $group->take(10));
    $order = ['birthday', 'graduation', 'wedding', 'baby-shower', 'engagement'];
    $tabs = collect($occasions)
        ->filter(fn ($occasion) => in_array($occasion->code, $order, true) || $groups->has($occasion->code))
        ->sortBy(fn ($occasion) => array_search($occasion->code, $order, true) === false ? 99 : array_search($occasion->code, $order, true))
        ->map(fn ($occasion) => ['code' => $occasion->code, 'name' => $occasion->getTranslation('name', app()->getLocale())])
        ->values();
    if ($groups->keys()->diff($tabs->pluck('code'))->isNotEmpty() || $tabs->isEmpty()) {
        $tabs->push(['code' => '*', 'name' => __('storefront.home.packages.all')]);
    }
    $active = $tabs->first(fn ($tab) => $groups->has($tab['code']))['code'] ?? $tabs->first()['code'];
    $visibleCount = $active === '*' ? min(10, collect($packages)->count()) : ($groups[$active] ?? collect())->count();
@endphp

<section id="homepage-packages" class="sf-home-section sf-packages-section" aria-labelledby="packages-heading" data-occasion-packages>
    <div class="sf-home-container">
        <x-home.section-header
            id="packages-heading"
            :eyebrow="__('storefront.home.packages.eyebrow')"
            :title="__('storefront.home.packages.heading')"
            :description="__('storefront.home.packages.sub')"
            :href="route('storefront.search', ['locale' => app()->getLocale()])"
            :link-label="__('storefront.home.packages.view_all')"
        />

        <div class="sf-package-occasion-filters" role="group" aria-label="{{ __('storefront.home.packages.filter_label') }}">
            @foreach ($tabs as $tab)
                <button type="button" data-package-filter="{{ $tab['code'] }}" aria-controls="occasion-package-grid" aria-pressed="{{ $active === $tab['code'] ? 'true' : 'false' }}">
                    <x-home.occasion-icon :code="$tab['code']" />
                    {{ $tab['name'] }}
                </button>
            @endforeach
        </div>

        <p class="sf-package-count" role="status" aria-live="polite" data-package-count data-count-label="{{ __('storefront.home.packages.showing', ['count' => ':count']) }}">{{ __('storefront.home.packages.showing', ['count' => $visibleCount]) }}</p>

        <div class="sf-occasion-package-grid" id="occasion-package-grid">
            @php
                $renderedIndex = 0;
            @endphp
            @foreach ($groups as $group)
                @foreach ($group as $package)
                    @php
                        $reference = $package['reference_card'] ?? [];
                        $hidden = $active === '*' ? $renderedIndex >= 10 : $active !== ($package['occasion_code'] ?? '');
                        $renderedIndex++;
                        $priceKind = ($package['fixed_budget'] ?? null) ? 'fixed' : ($package['min_budget'] && $package['max_budget'] ? 'range' : 'from');
                    @endphp
                    <a class="sf-occasion-package-card" href="{{ route('storefront.packages.show', ['locale' => app()->getLocale(), 'publicId' => $package['public_id']]) }}" data-package-occasion="{{ $package['occasion_code'] ?? '' }}" @if($hidden) hidden @endif>
                        <x-home.package-photo :package="$package" />
                        <div class="sf-occasion-package-body">
                            <h3>{{ $package['name'] }}</h3>
                            @if ($reference)
                                <p class="sf-occasion-package-meta"><span>{{ $reference['vendor'] }}</span><span aria-hidden="true">·</span><span>{{ $reference['location'] }}</span><span aria-hidden="true">·</span><span class="sf-occasion-package-rating"><span aria-hidden="true">★</span> {{ $reference['rating'] }}</span></p>
                                <ul class="sf-occasion-package-tags" aria-label="{{ $package['description'] }}">
                                    @foreach ($reference['tags'] as $tag)<li>{{ $tag }}</li>@endforeach
                                </ul>
                            @else
                                <p class="sf-occasion-package-description">{{ $package['description'] }}</p>
                            @endif
                            <div class="sf-occasion-package-footer">
                                @if ($package['min_budget'] || $package['max_budget'])
                                    <div data-package-price data-package-price-kind="{{ $priceKind }}">
                                        <span>{{ __('storefront.home.packages.'.($priceKind === 'from' ? 'from' : 'price')) }}</span>
                                        <strong>{{ $priceKind === 'range'
                                            ? __('storefront.home.packages.budget_range', ['min' => $package['min_budget'], 'max' => $package['max_budget']])
                                            : ($package['fixed_budget'] ?? $package['min_budget'] ?? $package['max_budget']) }}</strong>
                                    </div>
                                @endif
                                <span class="sf-occasion-package-arrow" aria-hidden="true">{{ app()->getLocale() === 'ar' ? '↖' : '↗' }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            @endforeach
        </div>
        <p class="sf-package-empty" data-package-empty @if($visibleCount > 0) hidden @endif>{{ __('storefront.home.packages.empty') }}</p>
    </div>
</section>

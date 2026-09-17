@extends('storefront.layouts.app')

@php
    use App\Modules\Catalog\Application\Actions\ListPublicCategoriesAction;
    use App\Modules\Catalog\Application\Actions\ListPublicOccasionsAction;
    use App\Modules\Catalog\Application\Actions\ListPublishedServicesAction;

    $blocks = collect($homepage['blocks'] ?? []);
    $blockByType = static fn (string $type): ?array => $blocks->first(
        static fn (array $block): bool => $block['block_type'] === $type,
    );

    $heroBlock = $blockByType('hero_carousel') ?? ['payload' => ['slides' => [[
        'eyebrow' => __('storefront.home.hero.eyebrow'),
        'headline' => __('storefront.home.hero.headline'),
        'sub' => __('storefront.home.hero.sub'),
        'cta_label' => __('storefront.home.hero.primary_cta'),
        'cta_url' => '/wizard',
    ]]]];
    $occasionBlock = $blockByType('featured_occasions') ?? [
        'public_id' => 'fallback-occasions',
        'payload' => [],
    ];
    $categoryBlock = $blockByType('featured_categories') ?? [
        'public_id' => 'fallback-categories',
        'payload' => [],
    ];
    $serviceBlock = $blockByType('featured_services');
    $vendorJoinBlock = $blockByType('vendor_join') ?? [
        'public_id' => 'fallback-vendor-join',
        'payload' => [],
    ];

    $featuredServiceIds = collect((array) data_get($serviceBlock, 'payload.service_public_ids', []))
        ->unique()
        ->values()
        ->all();

    $featuredServices = app(ListPublishedServicesAction::class)->execute($featuredServiceIds);
    $categories = app(ListPublicCategoriesAction::class)->execute();
    $occasions = app(ListPublicOccasionsAction::class)->execute();
    $featuredVendors = app(App\Modules\Discovery\Infrastructure\Repositories\VendorBrowsingRepository::class)
        ->featuredForStorefront(4);
    $packages = collect($homepage['packages'] ?? [])->take(3);
@endphp

@section('title', __('storefront.common.site_name').' · '.__('storefront.home.hero.headline'))

@section('content')
    <x-home.hero :block="$heroBlock" :fallback-image-url="$homepage['hero_image_url'] ?? null">
        <x-home.party-builder :occasions="$occasions" :categories="$categories" :cities="$cities" />
    </x-home.hero>

    <div class="sf-home-flow sf-home-v2">
        <x-home.taxonomy :block="$occasionBlock" :items="$occasions" kind="occasion" />
        <x-home.taxonomy :block="$categoryBlock" :items="$categories" kind="category" />

        @if ($serviceBlock)
            <x-home.featured-services :block="$serviceBlock" :services="$featuredServices" />
        @endif

        @if ($packages->isNotEmpty())
            <x-home.custom-packages :packages="$packages" />
        @endif

        <x-home.featured-vendors :vendors="$featuredVendors" />
        <x-home.how-it-works />
        <x-home.vendor-join :block="$vendorJoinBlock" />
    </div>
@endsection

@props(['type'])

@php
    use App\Modules\Catalog\Domain\Enums\ProductType;

    $badge = match ($type) {
        ProductType::Rental => ['label' => __('storefront.product_types.rental'), 'tone' => 'warning'],
        ProductType::Sale => ['label' => __('storefront.product_types.sale'), 'tone' => 'success'],
        ProductType::Digital => ['label' => __('storefront.product_types.digital'), 'tone' => 'info'],
    };
@endphp

<x-storefront.badge :tone="$badge['tone']" {{ $attributes }}>{{ $badge['label'] }}</x-storefront.badge>

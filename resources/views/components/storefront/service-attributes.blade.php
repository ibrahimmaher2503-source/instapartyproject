@props(['service'])

@php
    use App\Modules\Catalog\Domain\Enums\ProductType;

    $items = [];
    $categoryAttributes = $service instanceof App\Modules\Catalog\Domain\Models\Service
        ? app(App\Modules\Catalog\Application\Services\CategoryFieldPresenter::class)->forService($service)
        : [];
    foreach ($categoryAttributes as $attribute) {
        $items[] = [$attribute['label'], $attribute['value']];
    }

    if ($service->product_type === ProductType::Rental && $service->rentalDetail) {
        $detail = $service->rentalDetail;
        foreach (['length', 'width', 'height'] as $dimension) {
            $value = $detail->{$dimension.'_m'} ?? null;
            if ($value !== null) {
                $items[] = [__('catalog.dimension_'.$dimension), $value.' '.__('catalog.metres_unit')];
            }
        }
        if ($detail->default_rental_duration_hours) {
            $items[] = [__('storefront.service.rental_duration'), $detail->default_rental_duration_hours.' '.__('storefront.common.hours')];
        }
        if ($detail->setup_time_minutes) {
            $items[] = [__('storefront.service.setup_time'), $detail->setup_time_minutes.' '.__('storefront.common.minutes')];
        }
        if ($detail->teardown_time_minutes) {
            $items[] = [__('storefront.service.teardown_time'), $detail->teardown_time_minutes.' '.__('storefront.common.minutes')];
        }
        if ($detail->minimum_space_sqm) {
            $items[] = [__('storefront.service.minimum_space'), __('storefront.service.square_metres', ['value' => $detail->minimum_space_sqm])];
        }
        if ($detail->requires_electricity) {
            $items[] = [__('storefront.service.requires_electricity'), __('storefront.common.yes')];
        }
        if ($detail->requires_outdoor_space) {
            $items[] = [__('storefront.service.requires_outdoor_space'), __('storefront.common.yes')];
        }
        if ($detail->security_deposit_minor > 0) {
            $items[] = [__('storefront.service.security_deposit'), Brick\Money\Money::ofMinor((int) $detail->security_deposit_minor, (string) $detail->security_deposit_currency)->formatTo(app()->getLocale())];
        }
    } elseif ($service->product_type === ProductType::Sale && $service->saleDetail) {
        $detail = $service->saleDetail;
        if ($detail->lead_time_hours) {
            $items[] = [__('storefront.service.lead_time'), $detail->lead_time_hours.' '.__('storefront.common.hours')];
        }
    } elseif ($service->product_type === ProductType::Digital && $service->digitalDetail) {
        $detail = $service->digitalDetail;
        if ($detail->delivery_method) {
            $items[] = [__('storefront.service.delivery_method'), __('catalog.delivery_methods.'.(string) $detail->delivery_method)];
        }
        if ($detail->has_expiry && $detail->expiry_days_after_purchase) {
            $items[] = [__('storefront.service.expiry_note'), trans_choice('storefront.service.expiry_days', $detail->expiry_days_after_purchase, ['count' => $detail->expiry_days_after_purchase])];
        }
    }
@endphp

@if ($items !== [])
    <section {{ $attributes->class(['sf-service-attributes']) }} aria-labelledby="service-attributes-heading">
        <h2 id="service-attributes-heading">{{ __('storefront.service.attributes_heading') }}</h2>
        <dl>
            @foreach ($items as [$label, $value])
                <div>
                    <dt>{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
@endif

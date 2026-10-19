@props(['package'])
@php
    $fallback = asset(app(App\Modules\Shared\Application\Services\StorefrontImageFallback::class)->service(
        (string) $package['public_id'], 'rental', [(string) $package['name'], (string) $package['description']],
    ));
    $referencePhoto = (int) data_get($package, 'reference_card.photo', 0);
@endphp
@if ($referencePhoto >= 1 && $referencePhoto <= 10)
    <span class="sf-package-photo sf-package-photo--reference" data-package-reference-photo="{{ $referencePhoto }}" role="img" aria-label="{{ $package['name'] }}"></span>
@else
    <img class="sf-package-photo" src="{{ $package['hero_url'] ?: $fallback }}" alt="{{ $package['name'] }}" width="576" height="324" loading="lazy" onerror="this.onerror=null;this.src='{{ $fallback }}';">
@endif

@inject('designTokens', 'App\Modules\Shared\Application\Actions\GetActiveDesignTokensAction')

@php
    // Read the active token row in-process. The Next.js app fetched /theme/tokens
    // over HTTP and swallowed failures, which is why a backend hiccup rendered the
    // whole site in Times New Roman with no colours. Blade calls the Action directly,
    // and the Action already falls back to DesignTokenSchema::default(), so there is
    // no fetch to fail and no silent unstyled state.
    $tokens = $designTokens->execute()['tokens'];

    $colors = $tokens['colors'] ?? [];
    $typography = $tokens['typography'] ?? [];
    $fontUrl = $typography['googleFontUrl'] ?? null;

    // Only these four ramps are admin-editable; `neutral` maps onto the ink scale.
    $ramps = ['primary' => 'primary', 'secondary' => 'secondary', 'accent' => 'accent', 'neutral' => 'ink'];

    $declarations = [];

    foreach ($ramps as $sourceKey => $cssName) {
        foreach (($colors[$sourceKey] ?? []) as $shade => $hex) {
            $declarations[] = "--color-{$cssName}-{$shade}:{$hex}";
        }
    }

    foreach (['success', 'warning', 'danger', 'info'] as $status) {
        if (isset($colors[$status])) {
            $declarations[] = "--color-{$status}:{$colors[$status]}";
        }
    }

    foreach (($tokens['radius'] ?? []) as $key => $value) {
        $declarations[] = "--radius-{$key}:{$value}";
    }
@endphp

@if ($fontUrl && $fontUrl !== \App\Modules\Shared\Domain\Schemas\DesignTokenSchema::default()['typography']['googleFontUrl'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $fontUrl }}">
@endif

{{--
    Emitted AFTER @vite so these win over the compiled @theme defaults. Latin and
    Arabic families are separate custom properties; storefront.css re-points
    --font-sans at the Arabic one on [dir="rtl"] (the Tajawal Parity Rule), so no
    component ever chooses a font itself.
--}}
<style>
    :root {
        {!! implode(';', $declarations) !!};
        --sf-font-latin: '{{ $typography['fontFamilyBase'] ?? 'Manrope' }}', system-ui, sans-serif;
        --sf-font-arabic: '{{ $typography['fontFamilyArabic'] ?? 'Alexandria' }}', system-ui, sans-serif;
        --sf-font-heading: '{{ $typography['fontFamilyHeading'] ?? 'Manrope' }}', system-ui, sans-serif;
        --font-sans: var(--sf-font-latin);
    }

    [dir='rtl'] {
        --font-sans: var(--sf-font-arabic);
        --sf-font-heading: var(--sf-font-arabic);
    }
</style>

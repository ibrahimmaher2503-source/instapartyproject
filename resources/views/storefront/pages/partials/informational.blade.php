@php
    $sourceBody = (string) ($page['body'] ?? '');
    $articleHtml = str_contains($sourceBody, '<')
        ? (string) str($sourceBody)->sanitizeHtml()
        : nl2br(e($sourceBody));
    $headings = [];
    $headingCounts = [];

    $articleHtml = preg_replace_callback('/<h2(?:\s[^>]*)?>(.*?)<\/h2>/is', function (array $match) use (&$headings, &$headingCounts): string {
        $label = trim(strip_tags($match[1]));
        $base = Illuminate\Support\Str::slug($label) ?: 'section';
        $headingCounts[$base] = ($headingCounts[$base] ?? 0) + 1;
        $id = $base.($headingCounts[$base] > 1 ? '-'.$headingCounts[$base] : '');
        $headings[] = ['id' => $id, 'label' => $label];

        return '<h2 id="'.e($id).'">'.$match[1].'</h2>';
    }, $articleHtml) ?? $articleHtml;

    $showContents = in_array($slug, ['terms', 'privacy'], true) && count($headings) >= 4;
@endphp

<div class="sf-cms-page sf-info-page bg-[var(--sf-ivory)]">
    <section class="sf-info-hero bg-secondary-900 text-white">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-primary-300">{{ __('storefront.common.site_name') }}</p>
            <h1>{{ $page['title'] }}</h1>
            @if (filled($page['intro'] ?? null))
                <p class="sf-info-hero__intro">{{ $page['intro'] }}</p>
            @endif
        </div>
    </section>

    <main class="sf-info-shell">
        @if ($sourceBody !== '')
            @if ($showContents)
                <details class="sf-info-mobile-toc">
                    <summary>{{ __('storefront.pages.cms.open_contents') }}</summary>
                    <nav aria-label="{{ __('storefront.pages.cms.contents') }}">
                        @foreach ($headings as $heading)
                            <a href="#{{ $heading['id'] }}">{{ $heading['label'] }}</a>
                        @endforeach
                    </nav>
                </details>
            @endif

            <div @class(['sf-info-layout', 'sf-info-layout--with-toc' => $showContents])>
                @if ($showContents)
                    <aside class="sf-info-toc">
                        <nav aria-label="{{ __('storefront.pages.cms.contents') }}">
                            <strong>{{ __('storefront.pages.cms.contents') }}</strong>
                            @foreach ($headings as $heading)
                                <a href="#{{ $heading['id'] }}">{{ $heading['label'] }}</a>
                            @endforeach
                        </nav>
                    </aside>
                @endif

                <article @class(['sf-info-article', 'sf-info-article--about' => $slug === 'about'])>
                    <div class="sf-info-richtext">{!! $articleHtml !!}</div>
                </article>
            </div>
        @else
            <div class="sf-info-empty">
                <h2>{{ __('storefront.pages.content_pending.title') }}</h2>
                <p>{{ __('storefront.pages.content_pending.body') }}</p>
            </div>
        @endif
    </main>
</div>

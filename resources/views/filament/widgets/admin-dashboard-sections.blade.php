@php
    $toneClasses = [
        'primary' => ['surface' => 'bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-300', 'icon' => 'text-primary-600 dark:text-primary-300', 'dot' => 'bg-primary-500'],
        'success' => ['surface' => 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-300', 'icon' => 'text-success-600 dark:text-success-300', 'dot' => 'bg-success-500'],
        'warning' => ['surface' => 'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-300', 'icon' => 'text-warning-600 dark:text-warning-300', 'dot' => 'bg-warning-500'],
        'danger' => ['surface' => 'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-300', 'icon' => 'text-danger-600 dark:text-danger-300', 'dot' => 'bg-danger-500'],
        'info' => ['surface' => 'bg-info-50 text-info-700 dark:bg-info-400/10 dark:text-info-300', 'icon' => 'text-info-600 dark:text-info-300', 'dot' => 'bg-info-500'],
    ];
@endphp

<x-filament-widgets::widget>
    <div
        x-data="{
            mode: localStorage.getItem('instaparty-dashboard-focus') || '{{ $financialVisible ? 'financials' : 'operations' }}',
            rangeOpen: false,
            setMode(value) { this.mode = value; localStorage.setItem('instaparty-dashboard-focus', value) },
            closeRange() { this.rangeOpen = false },
        }"
        class="space-y-5"
        aria-label="{{ __('shared::dashboard.title') }}"
    >
        @if ($homepagePackages['visible'])
            <section class="overflow-hidden rounded-xl border border-emerald-200/70 bg-gradient-to-r from-emerald-50 via-white to-amber-50 p-4 dark:border-emerald-400/20 dark:from-emerald-950/30 dark:via-gray-900 dark:to-amber-950/20 sm:p-5" aria-labelledby="homepage-content-heading">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm"><x-filament::icon icon="heroicon-o-gift" class="h-5 w-5" /></span>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300">{{ __('shared::dashboard.homepage_packages.eyebrow') }}</p>
                            <h2 id="homepage-content-heading" class="mt-1 text-base font-bold text-gray-950 dark:text-white">{{ __('shared::dashboard.homepage_packages.title') }}</h2>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ __('shared::dashboard.homepage_packages.description', ['published' => $homepagePackages['published'], 'drafts' => $homepagePackages['drafts']]) }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 sm:justify-end">
                        <a href="{{ $homepagePackages['url'] }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200"><x-filament::icon icon="heroicon-o-squares-2x2" class="h-4 w-4" />{{ __('shared::dashboard.homepage_packages.manage') }}</a>
                        <a href="{{ $homepagePackages['create_url'] }}" class="inline-flex h-9 items-center gap-2 rounded-lg bg-emerald-700 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-800"><x-filament::icon icon="heroicon-o-plus" class="h-4 w-4" />{{ __('shared::dashboard.homepage_packages.create') }}</a>
                    </div>
                </div>
            </section>
        @endif

        <div class="flex flex-col gap-4 border-b border-gray-200/80 pb-4 dark:border-white/10 lg:flex-row lg:items-center lg:justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('shared::dashboard.choose_area') }}</p>
            <div class="flex items-center gap-2 self-start lg:self-auto">
                <div class="relative" @click.outside="closeRange()">
                    <button type="button" @click="rangeOpen = ! rangeOpen" :aria-expanded="rangeOpen" aria-haspopup="menu" class="inline-flex h-9 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:border-primary-300 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200 dark:hover:border-primary-400">
                        <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4 text-gray-400" />
                        <span>{{ __('shared::dashboard.range.seven_days') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-down" class="h-3.5 w-3.5 text-gray-400 transition" ::class="{ 'rotate-180': rangeOpen }" />
                    </button>
                    <div x-show="rangeOpen" x-cloak x-transition.origin.top.right role="menu" class="absolute end-0 z-20 mt-2 w-48 rounded-lg border border-gray-200 bg-white p-1.5 shadow-lg dark:border-white/10 dark:bg-gray-900">
                        <button type="button" role="menuitem" @click="closeRange()" class="flex w-full items-center justify-between rounded-md bg-primary-50 px-3 py-2 text-start text-xs font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">
                            <span>{{ __('shared::dashboard.range.seven_days') }}</span>
                            <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                        </button>
                        <div role="menuitem" aria-disabled="true" title="{{ __('shared::dashboard.empty_chart_caption') }}" class="flex cursor-not-allowed items-center justify-between rounded-md px-3 py-2 text-start text-xs text-gray-400 dark:text-gray-500">
                            <span>{{ __('shared::dashboard.range.thirty_days') }}</span>
                            <span class="text-[10px]">{{ __('shared::dashboard.empty_chart') }}</span>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="window.location.reload()" class="inline-flex h-9 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:border-primary-300 hover:text-primary-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200 dark:hover:border-primary-400"><x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4 text-gray-400" />{{ __('shared::dashboard.refresh') }}</button>
            </div>
        </div>

        @if ($financialVisible)
            <div class="flex flex-col gap-2 rounded-xl border border-gray-200/80 bg-gray-50/70 p-2.5 dark:border-white/10 dark:bg-white/[0.03] sm:flex-row sm:items-center sm:justify-between">
                <div class="px-2"><p class="text-xs font-bold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">{{ __('shared::dashboard.view_mode') }}</p><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('shared::dashboard.choose_area') }}</p></div>
                <div class="grid w-full gap-1 rounded-lg bg-gray-200/70 p-1 dark:bg-white/10 sm:w-auto sm:min-w-[22rem] sm:grid-cols-2" role="tablist">
                    <button type="button" role="tab" :aria-selected="mode === 'operations'" @click="setMode('operations')" class="flex h-10 items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" :style="mode === 'operations' ? 'background:#6d28d9;color:#fff;box-shadow:0 1px 2px rgba(15,23,42,.12)' : 'color:#374151'" @mouseenter="if (mode !== 'operations') $el.style.backgroundColor = '#fff'" @mouseleave="if (mode !== 'operations') $el.style.backgroundColor = 'transparent'"><x-filament::icon icon="heroicon-o-adjustments-horizontal" class="h-4 w-4" />{{ __('shared::dashboard.focus.operations') }}</button>
                    <button type="button" role="tab" :aria-selected="mode === 'financials'" @click="setMode('financials')" class="flex h-10 items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" :style="mode === 'financials' ? 'background:#6d28d9;color:#fff;box-shadow:0 1px 2px rgba(15,23,42,.12)' : 'color:#374151'" @mouseenter="if (mode !== 'financials') $el.style.backgroundColor = '#fff'" @mouseleave="if (mode !== 'financials') $el.style.backgroundColor = 'transparent'"><x-filament::icon icon="heroicon-o-banknotes" class="h-4 w-4" />{{ __('shared::dashboard.focus.financials') }}</button>
                </div>
            </div>
        @endif

        @if ($operationsVisible)
            <section x-show="mode === 'operations'" x-cloak class="space-y-5" aria-label="{{ __('shared::dashboard.focus.operations') }}">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach (array_slice($operations['kpis'], 0, 4) as $kpi)
                        @if ($kpi['visible'])
                            <a @if ($kpi['url']) href="{{ $kpi['url'] }}" @endif class="group flex min-h-[7.25rem] flex-col justify-between rounded-xl border border-gray-200/80 bg-white p-4 transition hover:border-primary-200 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-400/30">
                                <div class="flex items-center justify-between"><span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $toneClasses[$kpi['tone']]['surface'] }}"><x-filament::icon :icon="$kpi['icon']" class="h-4 w-4 {{ $toneClasses[$kpi['tone']]['icon'] }}" /></span><x-filament::icon icon="heroicon-m-arrow-up-right" class="h-3.5 w-3.5 text-gray-300 transition group-hover:text-primary-600" /></div>
                                <div class="flex items-end justify-between gap-2"><div><p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p><p class="mt-1 text-2xl font-bold leading-none tracking-tight text-gray-950 dark:text-white">{{ number_format($kpi['value']) }}</p></div><span class="mb-0.5 flex items-center gap-1 text-[11px] font-semibold {{ $toneClasses[$kpi['tone']]['icon'] }}"><span class="h-1.5 w-1.5 rounded-full {{ $toneClasses[$kpi['tone']]['dot'] }}"></span>{{ __('shared::dashboard.live_summary') }}</span></div>
                            </a>
                        @endif
                    @endforeach
                </div>
                @if (count($operations['kpis']) > 4)
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach (array_slice($operations['kpis'], 4) as $kpi)
                            @if ($kpi['visible'])
                                <a @if ($kpi['url']) href="{{ $kpi['url'] }}" @endif class="group flex min-h-[5.5rem] items-center gap-3 rounded-xl border border-gray-200/80 bg-white px-3.5 py-3 transition hover:border-primary-200 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-400/30">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $toneClasses[$kpi['tone']]['surface'] }}"><x-filament::icon :icon="$kpi['icon']" class="h-4 w-4 {{ $toneClasses[$kpi['tone']]['icon'] }}" /></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-xs font-medium text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</span><span class="mt-0.5 block text-xl font-bold leading-none tracking-tight text-gray-950 dark:text-white">{{ number_format($kpi['value']) }}</span></span>
                                    <x-filament::icon icon="heroicon-m-arrow-up-right" class="h-3.5 w-3.5 shrink-0 text-gray-300 transition group-hover:text-primary-600" />
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif

                <div class="grid gap-4 xl:grid-cols-12">
                    @include('filament.widgets.partials.dashboard-chart', ['chart' => $operations['chart'], 'panelClass' => 'xl:col-span-8'])
                    <div class="rounded-xl border border-gray-200/80 bg-white p-4 dark:border-white/10 dark:bg-gray-900 xl:col-span-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-white/10"><div><h3 class="text-base font-bold text-gray-950 dark:text-white">{{ __('shared::dashboard.operations.needs_attention') }}</h3><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('shared::dashboard.choose_area') }}</p></div><span class="rounded-full bg-danger-50 px-2 py-1 text-xs font-bold text-danger-700 dark:bg-danger-400/10 dark:text-danger-300">{{ number_format(collect($operations['attention'])->sum('value')) }}</span></div>
                        <div class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($operations['attention'] as $item)
                                @if ($item['visible'])
                                    <a @if ($item['url']) href="{{ $item['url'] }}" @endif class="group flex items-center gap-3 py-3 first:pt-4 last:pb-0"><span class="h-2 w-2 shrink-0 rounded-full {{ $toneClasses[$item['tone']]['dot'] }}"></span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $item['label'] }}</span><span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ number_format($item['value']) }} {{ __('shared::dashboard.operations.services') }}</span></span><x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4 text-gray-300 transition group-hover:text-primary-600 rtl:rotate-180" /></a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <div class="rounded-xl border border-gray-200/80 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                        <div class="mb-3 flex items-center justify-between"><h3 class="text-base font-bold text-gray-950 dark:text-white">{{ __('shared::dashboard.operations.upcoming_bookings') }}</h3><a href="{{ \App\Modules\Booking\Filament\Resources\BookingResource::getUrl('index') }}" class="text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300">{{ __('shared::dashboard.view_all') }}</a></div>
                        @if (count($operations['upcoming']) === 0)
                            <div class="flex items-center gap-3 rounded-lg bg-gray-50 px-3 py-4 dark:bg-white/[0.03]"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-300"><x-filament::icon icon="heroicon-o-calendar-days" class="h-5 w-5" /></span><div><p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ __('shared::dashboard.empty_upcoming') }}</p><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('shared::dashboard.empty_chart_caption') }}</p></div></div>
                        @else
                            <div class="divide-y divide-gray-100 dark:divide-white/10">@foreach ($operations['upcoming'] as $booking)<a href="{{ \App\Modules\Booking\Filament\Resources\BookingResource::getUrl('index') }}" class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0"><span class="flex h-7 w-7 items-center justify-center rounded-md bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-300"><x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4" /></span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $booking['reference'] }}</span><span class="text-xs text-gray-500 dark:text-gray-400">{{ $booking['date'] }} · {{ $booking['services'] }} {{ __('shared::dashboard.operations.services') }} · {{ $booking['vendors'] }} {{ __('shared::dashboard.operations.vendors') }}</span></span><span class="text-xs font-semibold text-success-700 dark:text-success-300">{{ $booking['status'] }}</span></a>@endforeach</div>
                        @endif
                    </div>
                    <div class="rounded-xl border border-gray-200/80 bg-white p-4 dark:border-white/10 dark:bg-gray-900"><div class="mb-3 flex items-center justify-between"><h3 class="text-base font-bold text-gray-950 dark:text-white">{{ __('shared::dashboard.operations.negotiation_monitoring') }}</h3><a href="{{ \App\Modules\Booking\Filament\Resources\BookingsMonitorResource::getUrl('index') }}" class="text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300">{{ __('shared::dashboard.view_all') }}</a></div><div class="divide-y divide-gray-100 dark:divide-white/10">@foreach ($operations['negotiations'] as $item)<a @if ($item['url']) href="{{ $item['url'] }}" @endif class="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0"><span class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>{{ $item['label'] }}</span><span class="text-sm font-bold text-gray-950 dark:text-white">{{ number_format($item['value']) }}</span></a>@endforeach</div></div>
                </div>

                <div class="rounded-xl border border-gray-200/80 bg-white p-4 dark:border-white/10 dark:bg-gray-900"><div class="mb-3 flex items-center justify-between"><h3 class="text-base font-bold text-gray-950 dark:text-white">{{ __('shared::dashboard.operations.vendor_operations') }}</h3><a href="{{ \App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource::getUrl('index') }}" class="text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300">{{ __('shared::dashboard.view_all') }} ←</a></div><div class="grid gap-2 sm:grid-cols-4">@foreach ([['label' => __('shared::dashboard.operations.pending_approvals'), 'value' => $operations['kpis'][4]['value'] ?? 0, 'tone' => 'warning'], ['label' => __('shared::dashboard.attention.coverage_issues'), 'value' => 0, 'tone' => 'info'], ['label' => __('shared::dashboard.operations.health_issues'), 'value' => 0, 'tone' => 'success'], ['label' => __('shared::dashboard.attention.provider_issues'), 'value' => $operations['attention'][3]['value'] ?? 0, 'tone' => 'danger']] as $item)<div class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2.5 dark:bg-white/[0.03]"><span class="truncate text-xs text-gray-600 dark:text-gray-300">{{ $item['label'] }}</span><span class="text-base font-bold {{ $toneClasses[$item['tone']]['icon'] }}">{{ number_format($item['value']) }}</span></div>@endforeach</div></div>
            </section>
        @endif

        @if ($financialVisible)
            <section x-show="mode === 'financials'" x-cloak class="space-y-5" aria-label="{{ __('shared::dashboard.focus.financials') }}">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach (array_slice($financials['kpis'], 0, 4) as $kpi)
                        <a @if ($kpi['url']) href="{{ $kpi['url'] }}" @endif title="{{ $kpi['description'] ?? '' }}" class="group flex min-h-[7.25rem] flex-col justify-between rounded-xl border border-gray-200/80 bg-white p-4 transition hover:border-primary-200 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-400/30"><div class="flex items-center justify-between"><span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $toneClasses[$kpi['tone']]['surface'] }}"><x-filament::icon :icon="$kpi['icon']" class="h-4 w-4 {{ $toneClasses[$kpi['tone']]['icon'] }}" /></span><x-filament::icon icon="heroicon-m-arrow-up-right" class="h-3.5 w-3.5 text-gray-300 group-hover:text-primary-600" /></div><div><p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p><p class="mt-1 text-xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $kpi['value'] }}</p><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kpi['description'] ?? '' }}</p></div></a>
                    @endforeach
                </div>
                <div class="grid gap-4 xl:grid-cols-12">@include('filament.widgets.partials.dashboard-chart', ['chart' => $financials['chart'], 'panelClass' => 'xl:col-span-8'])<div class="rounded-xl border border-gray-200/80 bg-white p-4 dark:border-white/10 dark:bg-gray-900 xl:col-span-4"><div class="mb-3 flex items-center justify-between"><h3 class="text-base font-bold text-gray-950 dark:text-white">{{ __('shared::dashboard.financials.health') }}</h3><span class="h-2 w-2 rounded-full bg-success-500"></span></div><div class="divide-y divide-gray-100 dark:divide-white/10">@foreach ($financials['statuses'] as $status)<a href="{{ $status['url'] }}" class="flex items-center justify-between py-3 first:pt-0 last:pb-0"><span class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><span class="h-1.5 w-1.5 rounded-full {{ $toneClasses[$status['tone']]['dot'] }}"></span>{{ $status['label'] }}</span><span class="font-bold text-gray-950 dark:text-white">{{ number_format($status['value']) }}</span></a>@endforeach</div></div></div>
                <div class="grid gap-4 xl:grid-cols-3"><div class="rounded-xl border border-gray-200/80 bg-white p-4 dark:border-white/10 dark:bg-gray-900"><h3 class="mb-3 text-base font-bold text-gray-950 dark:text-white">{{ __('shared::dashboard.financials.summary') }}</h3><div class="grid grid-cols-2 gap-2">@foreach ($financials['summary'] as $item)<div class="rounded-lg bg-gray-50 px-3 py-2.5 dark:bg-white/[0.03]"><p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p><p class="mt-1 text-sm font-bold text-gray-950 dark:text-white">{{ $item['value'] }}</p></div>@endforeach</div></div><div class="rounded-xl border border-gray-200/80 bg-white p-4 dark:border-white/10 dark:bg-gray-900"><h3 class="mb-3 text-base font-bold text-gray-950 dark:text-white">{{ __('shared::dashboard.financials.top_vendors') }}</h3><div class="divide-y divide-gray-100 dark:divide-white/10">@forelse ($financials['vendors'] as $index => $vendor)<div class="flex items-center gap-2 py-2 first:pt-0 last:pb-0"><span class="text-xs font-bold text-gray-400">{{ $index + 1 }}</span><span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-200">{{ $vendor['name'] }}</span><span class="text-xs font-semibold text-gray-900 dark:text-white">{{ $vendor['revenue'] }}</span></div>@empty<p class="text-sm text-gray-500 dark:text-gray-400">{{ __('shared::dashboard.empty_state') }}</p>@endforelse</div></div><div class="rounded-xl border border-gray-200/80 bg-white p-4 dark:border-white/10 dark:bg-gray-900"><h3 class="mb-3 text-base font-bold text-gray-950 dark:text-white">{{ __('shared::dashboard.financials.vendor_payouts') }}</h3><div class="flex items-center justify-between rounded-lg bg-warning-50 px-3 py-3 text-warning-800 dark:bg-warning-400/10 dark:text-warning-200"><span class="text-sm">{{ __('shared::dashboard.financials.outstanding') }}</span><span class="font-bold">{{ $financials['summary'][3]['value'] ?? '—' }}</span></div><a href="{{ \App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource::getUrl('index') }}" class="mt-3 block text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300">{{ __('shared::dashboard.view_all') }} ←</a></div></div>
                <div class="rounded-xl border border-gray-200/80 bg-white p-4 dark:border-white/10 dark:bg-gray-900"><div class="mb-3 flex items-center justify-between"><h3 class="text-base font-bold text-gray-950 dark:text-white">{{ __('shared::dashboard.financials.attention') }}</h3><span class="text-xs text-gray-500 dark:text-gray-400">{{ __('shared::dashboard.view_all') }}</span></div><div class="grid gap-2 sm:grid-cols-3">@foreach ($financials['attention'] as $item)<a href="{{ $item['url'] }}" class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2.5 dark:bg-white/[0.03]"><span class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><span class="h-1.5 w-1.5 rounded-full {{ $toneClasses[$item['tone']]['dot'] }}"></span>{{ $item['label'] }}</span><span class="font-bold text-gray-950 dark:text-white">{{ number_format($item['value']) }}</span></a>@endforeach</div></div>
            </section>
        @endif
    </div>
</x-filament-widgets::widget>

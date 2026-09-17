@php
    $hasChartData = collect($chart['series'])->contains(fn (array $series): bool => collect($series['values'])->contains(fn (array $point): bool => $point['value'] > 0));
@endphp
<section class="{{ $panelClass ?? '' }} rounded-xl border border-gray-200/80 bg-white p-4 dark:border-white/10 dark:bg-gray-900" aria-label="{{ $chart['title'] }}">
    <div class="flex items-start justify-between gap-3"><div><h3 class="text-base font-bold text-gray-950 dark:text-white">{{ $chart['title'] }}</h3><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $chart['caption'] }}</p></div><div class="flex shrink-0 items-center gap-1 rounded-md border border-gray-200 p-0.5 text-[11px] dark:border-white/10"><button type="button" @click="rangeOpen = false" class="rounded bg-primary-50 px-2 py-1 font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">{{ __('shared::dashboard.range.seven_days') }}</button><button type="button" disabled title="{{ __('shared::dashboard.empty_chart_caption') }}" class="cursor-not-allowed px-2 py-1 text-gray-400">{{ __('shared::dashboard.range.thirty_days') }}</button></div></div>
    <div class="mt-4 overflow-hidden rounded-lg bg-gray-50/70 p-3 dark:bg-white/[0.03]">
        @if (count($chart['series']) > 0 && $hasChartData)
            <div class="mb-3 flex flex-wrap gap-x-5 gap-y-2">@foreach ($chart['series'] as $series)<span class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300"><span class="h-2 w-2 rounded-full {{ ['primary' => 'bg-primary-500', 'success' => 'bg-success-500', 'warning' => 'bg-warning-500', 'danger' => 'bg-danger-500', 'info' => 'bg-info-500'][$series['color']] ?? 'bg-primary-500' }}" aria-hidden="true"></span>{{ $series['label'] }}</span>@endforeach</div>
            <svg viewBox="0 0 1000 260" class="h-56 w-full" role="img" aria-label="{{ $chart['title'] }}">
                <path d="M0 220H1000 M0 125H1000 M0 30H1000" stroke="currentColor" class="text-gray-200 dark:text-white/10" stroke-width="1" stroke-dasharray="4 6" fill="none" />
                @foreach ($chart['series'] as $series)
                    <polyline points="{{ $chartPoints($series['values']) }}" fill="none" stroke="{{ ['primary' => '#6d28d9', 'success' => '#16a34a', 'warning' => '#f59e0b', 'danger' => '#dc2626', 'info' => '#0ea5e9'][$series['color']] ?? '#6d28d9' }}" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                @endforeach
            </svg>
            <div class="flex justify-between gap-2 text-[11px] text-gray-400">@foreach (($chart['series'][0]['values'] ?? []) as $point)<span>{{ $point['label'] }}</span>@endforeach</div>
        @else
            <div class="flex min-h-44 items-center justify-center gap-3 text-start"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-300"><x-filament::icon icon="heroicon-o-chart-bar" class="h-5 w-5" /></span><div><p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ __('shared::dashboard.empty_chart') }}</p><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('shared::dashboard.empty_chart_caption') }}</p></div></div>
        @endif
    </div>
</section>

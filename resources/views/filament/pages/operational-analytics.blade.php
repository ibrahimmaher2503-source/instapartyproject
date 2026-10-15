<x-filament-panels::page>
    <label class="block space-y-2">
        <span>{{ __('shared::operations.period') }}</span>
        <select wire:model.live="days" class="rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            @foreach ([7, 30, 90] as $days)
                <option value="{{ $days }}">{{ __('shared::operations.days'.$days) }}</option>
            @endforeach
        </select>
    </label>
    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('shared::operations.analytics_basis') }}</p>
    <div class="overflow-x-auto">
        <table class="w-full text-start text-sm">
            <thead><tr>
                <th class="p-3 text-start">{{ __('shared::operations.analytics') }}</th>
                @foreach ($periods as $name => $period)
                    <th class="p-3 text-start">{{ __('shared::operations.'.$name) }}<span class="block text-xs font-normal">{{ $period['from'] }} — {{ $period['until'] }}</span></th>
                @endforeach
            </tr></thead>
            <tbody>
            @foreach (['drafts', 'submitted', 'paid', 'completed', 'cancelled', 'active_vendors', 'new_vendors', 'approved_vendors', 'conversion', 'payment_conversion'] as $metric)
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <th class="p-3 text-start">{{ __('shared::operations.'.$metric) }}</th>
                    @foreach ($periods as $period)
                        <td class="p-3">{{ $period[$metric] === null ? __('shared::operations.not_available') : $period[$metric].(str_contains($metric, 'conversion') ? '%' : '') }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if ($periods['current']['drafts'] === 0 && $periods['current']['new_vendors'] === 0)
        <p>{{ __('shared::operations.no_data') }}</p>
    @endif
</x-filament-panels::page>

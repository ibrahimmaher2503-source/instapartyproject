<x-filament-panels::page>
    <p>{{ __('catalog::vendor_calendar.description') }}</p>
    @if ($services->isEmpty())
        <p>{{ __('catalog::vendor_calendar.empty') }}</p>
    @else
        <label for="calendar-service">{{ __('catalog::vendor_calendar.service') }}</label>
        <select id="calendar-service" wire:model.live="servicePublicId" class="rounded-lg border-gray-300 dark:bg-gray-900 dark:text-white">
            @foreach ($services as $option)
                <option value="{{ $option->public_id }}">{{ $option->getTranslation('name', app()->getLocale()) }}</option>
            @endforeach
        </select>
        @if ($service)
            <x-filament::link :href="\App\Modules\Catalog\Filament\Vendor\Pages\VendorServiceAvailabilityPage::getUrl(['service' => $service->public_id], panel: 'vendor')">
                {{ __('catalog::vendor_calendar.manage') }}
            </x-filament::link>
        @endif
        <h2 class="text-lg font-semibold">{{ $monthLabel }} · {{ $timezone }}</h2>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse" style="min-width: 680px; table-layout: fixed">
                <thead><tr>
                    @foreach ($weekdays as $weekday)
                        <th scope="col" class="p-2">{{ $weekday }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @foreach (array_chunk($days, 7) as $week)
                        <tr>
                            @foreach ($week as $day)
                                <td data-calendar-date="{{ $day['date']->toDateString() }}" class="border border-gray-200 p-2 align-top dark:border-gray-700 {{ $day['current'] ? '' : 'opacity-50' }}">
                                    <time datetime="{{ $day['date']->toDateString() }}">{{ $day['date']->format('j') }}</time>
                                    @forelse ($day['intervals'] as $interval)
                                        <p class="mt-2 text-xs text-warning-700 dark:text-warning-400">{{ $interval['label'] }}<br>{{ $interval['starts']->format('d/m H:i') }} – {{ $interval['ends']->format('d/m H:i') }}</p>
                                    @empty
                                        <p class="mt-2 text-xs text-gray-500">{{ __('catalog::vendor_calendar.clear') }}</p>
                                    @endforelse
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>

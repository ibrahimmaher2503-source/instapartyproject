<x-filament-panels::page>
    <x-filament::section :heading="__('settlement.reconciliation_dashboard.open_high_findings')">
        <p>{{ number_format($this->getOpenHighFindingsCount()) }}</p>
    </x-filament::section>

    <x-filament::section :heading="__('settlement.reconciliation_dashboard.auto_repaired_today')">
        <p>{{ number_format($this->getTotalAutoRepairedToday()) }}</p>
    </x-filament::section>

    <x-filament::section :heading="__('settlement.reconciliation_dashboard.runs_last_7_days')">
        <p>{{ number_format(collect($this->getLast7DaysRunTrend())->sum('runs')) }}</p>
    </x-filament::section>

    <x-filament::section :heading="__('settlement.reconciliation_dashboard.run_trend_heading')">
        <table>
            <thead>
                <tr>
                    <th>{{ __('settlement.reconciliation_dashboard.date') }}</th>
                    <th>{{ __('settlement.reconciliation_dashboard.runs') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->getLast7DaysRunTrend() as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['runs'] > 0 ? number_format($row['runs']) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>

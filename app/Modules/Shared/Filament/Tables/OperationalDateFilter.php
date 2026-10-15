<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

final class OperationalDateFilter
{
    public static function make(string $column = 'created_at'): Filter
    {
        return Filter::make('date_range')->label(__('shared::operations.date_range'))
            ->form([
                DatePicker::make('from')->label(__('shared::operations.from')),
                DatePicker::make('until')->label(__('shared::operations.until'))->afterOrEqual('from'),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '<=', $date)))
            ->indicateUsing(fn (array $data): array => array_filter([
                filled($data['from'] ?? null) ? __('shared::operations.from').': '.$data['from'] : null,
                filled($data['until'] ?? null) ? __('shared::operations.until').': '.$data['until'] : null,
            ]));
    }
}

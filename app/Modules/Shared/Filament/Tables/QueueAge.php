<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class QueueAge
{
    public static function column(string $queue, string $timestamp = 'created_at'): TextColumn
    {
        return TextColumn::make('queue_age')
            ->label(__('shared::operations.age'))
            ->state(fn (Model $record): ?string => $record->getAttribute($timestamp)?->diffForHumans())
            ->tooltip(__('shared::operations.sla_hours', ['hours' => config('operations.queue_sla_hours.'.$queue, 48)]))
            ->badge()
            ->color(fn (Model $record): string => $record->getAttribute($timestamp)?->lessThanOrEqualTo(now()->subHours((int) config('operations.queue_sla_hours.'.$queue, 48))) ? 'danger' : 'gray');
    }

    public static function overdue(string $queue, string $timestamp = 'created_at'): Filter
    {
        return Filter::make('overdue')->label(__('shared::operations.overdue'))
            ->query(fn (Builder $query): Builder => $query->where($timestamp, '<=', now()->subHours((int) config('operations.queue_sla_hours.'.$queue, 48))));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Tables;

use BackedEnum;
use DateTimeInterface;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

final class OperationalExport
{
    /** @param class-string<\Filament\Resources\Resource> $resource
     * @param  list<string>  $fields  Public references, states, dates and integer money only.
     */
    public static function make(string $resource, array $fields): ExportAction
    {
        return ExportAction::make('export')
            ->label(__('shared::operations.export'))
            ->visible(fn (): bool => $resource::canViewAny() && auth()->user()?->can('export_operational_records') === true)
            ->requiresConfirmation()
            ->modalDescription(__('shared::operations.export_limit'))
            ->exports([
                ExcelExport::make('operational')
                    ->useTableQuery()
                    ->withWriterType(Excel::CSV)
                    ->withFilename(fn (): string => class_basename($resource).'-'.now()->format('Y-m-d-His'))
                    ->withColumns(array_map(fn (string $field): Column => Column::make($field)
                        ->formatStateUsing(fn (mixed $state): mixed => self::safeCell($state)), $fields))
                    ->modifyQueryUsing(function (Builder $query, HasTable $livewire) use ($resource): Builder {
                        abort_unless($resource::canViewAny() && auth()->user()?->can('export_operational_records') === true, 403);
                        $count = (clone $query)->reorder()->count();
                        if ($count > 5000) {
                            throw ValidationException::withMessages(['export' => __('shared::operations.export_limit')]);
                        }
                        DB::table('audit_logs')->insert([
                            'public_id' => (string) Str::ulid(), 'auditable_type' => $resource::getModel(),
                            'auditable_id' => 0, 'user_id' => auth()->id(), 'action' => 'operational_records_exported',
                            'changes' => json_encode(['resource' => class_basename($resource), 'rows' => $count], JSON_THROW_ON_ERROR),
                            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(),
                        ]);

                        return $query;
                    }),
            ]);
    }

    public static function safeCell(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) === 1 ? "'".$value : $value;
    }
}

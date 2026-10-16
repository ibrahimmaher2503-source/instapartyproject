<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Tables;

use Closure;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ScopedBulkAction
{
    /** @param class-string<\Filament\Resources\Resource> $resource
     * @param  Closure(Model, array<string, mixed>=): mixed  $operation
     */
    public static function make(string $name, string $label, string $permission, string $resource, Closure $operation): BulkAction
    {
        return BulkAction::make($name)->label($label)
            ->visible(fn (): bool => $resource::canViewAny() && auth()->user()?->can($permission) === true)
            ->requiresConfirmation()->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data) use ($permission, $resource, $operation): void {
                abort_unless($resource::canViewAny() && auth()->user()?->can($permission) === true, 403);
                if ($records->count() > 50) {
                    throw ValidationException::withMessages(['selectedTableRecords' => __('shared::operations.bulk_limit')]);
                }
                $results = [];
                foreach ($records as $record) {
                    try {
                        $record = $resource::getEloquentQuery()->whereKey($record->getKey())->firstOrFail();
                        abort_unless($resource::canView($record), 403);
                        if ($data === []) {
                            $operation($record);
                        } else {
                            $operation($record, $data);
                        }
                        $results[] = $record->getAttribute('public_id').': '.__('shared::operations.processed');
                    } catch (Throwable $exception) {
                        report($exception);
                        $results[] = $record->getAttribute('public_id').': '.__('shared::operations.not_processed');
                    }
                }
                Notification::make()->title(__('shared::operations.bulk_results'))->body(implode("\n", $results))->persistent()->send();
            });
    }
}

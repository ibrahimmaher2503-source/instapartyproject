<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Actions;

use App\Modules\Catalog\Application\Actions\ArchiveServiceAction;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Catalog\Domain\States\ServiceStatus\ArchivedState;
use App\Modules\Identity\Domain\Models\User;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class ArchiveServiceFilamentAction
{
    public static function make(): Action
    {
        return Action::make('archive')
            ->label(__('catalog.archive_selected'))
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (Service $record): void {
                $user = auth()->user();
                abort_unless($user instanceof User, 403);

                app(ArchiveServiceAction::class)->execute($record, $user);

                Notification::make()
                    ->title(__('catalog.archive_selected_success', ['count' => 1]))
                    ->success()
                    ->send();
            })
            ->visible(function (Service $record): bool {
                $user = auth()->user();

                return $user instanceof User
                    && $record->status->canTransitionTo(ArchivedState::class, $user->id)
                    && app(ServicePolicy::class)->archive($user, $record);
            });
    }
}

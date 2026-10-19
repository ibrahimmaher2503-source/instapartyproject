<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Filament\Resources\PromoCodeResource\Pages;

use App\Modules\Promotions\Application\Actions\DeleteUnusedPromoCodeAction;
use App\Modules\Promotions\Domain\Models\PromoCode;
use App\Modules\Promotions\Filament\Resources\PromoCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPromoCode extends EditRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->using(function (PromoCode $record): bool {
            $deleted = app(DeleteUnusedPromoCodeAction::class)->execute($record);
            if (! $deleted) {
                Notification::make()->warning()->title(__('promotions::promotions.used_code_cannot_delete'))->send();
            }

            return $deleted;
        })];
    }
}

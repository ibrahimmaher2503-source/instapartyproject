<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource\Pages;

use App\Modules\Subscriptions\Application\Actions\InvalidatePlanFeaturesCache;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscriptionPlan extends EditRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(InvalidatePlanFeaturesCache::class)->execute($this->record->id);
    }
}

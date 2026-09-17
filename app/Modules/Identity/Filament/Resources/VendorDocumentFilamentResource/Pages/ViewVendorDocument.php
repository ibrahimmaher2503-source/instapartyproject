<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages;

use App\Modules\Identity\Application\Actions\GenerateDocumentSignedUrlAction;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorDocument extends ViewRecord
{
    protected static string $resource = VendorDocumentFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openDocument')
                ->label(__('identity.actions.open_document'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->action(fn () => redirect()->away(
                    app(GenerateDocumentSignedUrlAction::class)->execute($this->getRecord(), auth()->user())
                )),
            EditAction::make(),
        ];
    }
}

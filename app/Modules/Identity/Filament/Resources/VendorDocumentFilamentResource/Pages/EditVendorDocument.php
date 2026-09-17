<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages;

use App\Modules\Identity\Application\Actions\GenerateDocumentSignedUrlAction;
use App\Modules\Identity\Application\Actions\SetDocumentExpiryAction;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EditVendorDocument extends EditRecord
{
    protected static string $resource = VendorDocumentFilamentResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var VendorDocument $record */
        return app(SetDocumentExpiryAction::class)->execute(
            $record,
            Carbon::parse((string) ($data['expires_at'] ?? '')),
            (bool) ($data['is_critical'] ?? false),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openDocument')
                ->label(__('identity.actions.open_document'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->action(fn () => redirect()->away(
                    app(GenerateDocumentSignedUrlAction::class)->execute($this->getRecord(), auth()->user())
                )),
            DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription(__('identity.compliance.delete_document_warning')),
        ];
    }
}

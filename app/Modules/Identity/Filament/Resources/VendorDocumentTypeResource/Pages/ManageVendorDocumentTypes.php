<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorDocumentTypeResource\Pages;

use App\Modules\Identity\Application\Actions\SaveVendorDocumentTypeAction;
use App\Modules\Identity\Domain\Models\VendorDocumentType;
use App\Modules\Identity\Filament\Resources\VendorDocumentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVendorDocumentTypes extends ManageRecords
{
    protected static string $resource = VendorDocumentTypeResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return VendorDocumentTypeResource::canViewAny();
    }

    public function getSubheading(): ?string
    {
        return __('identity::document_types.description');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->using(fn (array $data): VendorDocumentType => app(SaveVendorDocumentTypeAction::class)->execute(null, $data))];
    }
}

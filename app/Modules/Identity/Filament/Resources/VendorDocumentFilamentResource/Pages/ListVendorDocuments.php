<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages;

use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListVendorDocuments extends ListRecords
{
    protected static string $resource = VendorDocumentFilamentResource::class;

    public function mount(): void
    {
        parent::mount();
        $count = VendorDocument::query()->latestPerType()->where('status', 'approved')->expiringWithinDays(30)->count();
        if ($count > 0) {
            Notification::make()->title(__('identity.compliance.expiring_alert', ['count' => $count]))->warning()->send();
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages;

use App\Modules\Catalog\Filament\Resources\DigitalServiceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PendingDigitalServicesPage extends ListRecords
{
    protected static string $resource = DigitalServiceResource::class;

    public function getTitle(): string
    {
        return __('catalog.pending_digital_services');
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->emptyStateHeading(__('catalog.pending_queue_empty_digital'))
            ->emptyStateDescription(__('catalog.pending_queue_empty_digital_description'));
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ?->pendingReview()
            ->whereNull('deleted_at');
    }

    protected function getTableFilters(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

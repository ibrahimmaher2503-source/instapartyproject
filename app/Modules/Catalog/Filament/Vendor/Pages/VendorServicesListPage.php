<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Pages;

use App\Modules\Catalog\Application\Actions\CloneServiceAction;
use App\Modules\Catalog\Application\Actions\SubmitServiceForReviewAction;
use App\Modules\Catalog\Application\Actions\VendorArchiveServiceAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ServiceState;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class VendorServicesListPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'services';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'vendor-portal.pages.vendor-services-list';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.services.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.services.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addRental')
                ->label(__('vendor-portal.services.create_rental'))
                ->icon('heroicon-o-plus')
                ->color('warning')
                ->url(VendorRentalServiceResource::getUrl('create', panel: 'vendor')),
            Action::make('addSale')
                ->label(__('vendor-portal.services.create_sale'))
                ->icon('heroicon-o-plus')
                ->color('success')
                ->url(VendorSaleServiceResource::getUrl('create', panel: 'vendor')),
            Action::make('addDigital')
                ->label(__('vendor-portal.services.create_digital'))
                ->icon('heroicon-o-plus')
                ->color('info')
                ->url(VendorDigitalServiceResource::getUrl('create', panel: 'vendor')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Service::query()
                    ->where('vendor_profile_id', $this->getVendorProfile()->id)
                    ->latest()
            )
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->emptyStateHeading(__('vendor-portal.services.empty_heading'))
            ->emptyStateDescription(__('vendor-portal.services.empty_description'))
            ->emptyStateActions([
                TableAction::make('createRental')
                    ->label(__('vendor-portal.services.create_rental'))
                    ->icon('heroicon-o-plus')
                    ->color('warning')
                    ->url(VendorRentalServiceResource::getUrl('create', panel: 'vendor')),
                TableAction::make('createSale')
                    ->label(__('vendor-portal.services.create_sale'))
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->url(VendorSaleServiceResource::getUrl('create', panel: 'vendor')),
                TableAction::make('createDigital')
                    ->label(__('vendor-portal.services.create_digital'))
                    ->icon('heroicon-o-plus')
                    ->color('info')
                    ->url(VendorDigitalServiceResource::getUrl('create', panel: 'vendor')),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label(__('catalog.name'))
                    ->formatStateUsing(fn (Service $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->limit(40)
                    ->url(fn (Service $record): string => match ($record->product_type) {
                        ProductType::Rental => VendorRentalServiceResource::getUrl('edit', ['record' => $record->public_id], panel: 'vendor'),
                        ProductType::Sale => VendorSaleServiceResource::getUrl('edit', ['record' => $record->public_id], panel: 'vendor'),
                        ProductType::Digital => VendorDigitalServiceResource::getUrl('edit', ['record' => $record->public_id], panel: 'vendor'),
                    }),
                TextColumn::make('public_id')
                    ->label(__('catalog.public_id'))
                    ->copyable()
                    ->searchable()
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('product_type')
                    ->label(__('vendor-portal.services.type'))
                    ->badge()
                    ->color(fn (ProductType $state) => match ($state) {
                        ProductType::Rental => 'warning',
                        ProductType::Sale => 'success',
                        ProductType::Digital => 'info',
                    })
                    ->formatStateUsing(fn (ProductType $state) => $state->label()),
                TextColumn::make('status')
                    ->label(__('vendor-portal.services.status'))
                    ->badge()
                    ->color(fn (mixed $state): string => $state instanceof ServiceState
                        ? (ServiceStatus::tryFrom($state->getValue())?->color() ?? 'gray')
                        : 'gray')
                    ->formatStateUsing(function (mixed $state): string {
                        if (! ($state instanceof ServiceState)) {
                            return '—';
                        }

                        return match (ServiceStatus::tryFrom($state->getValue())) {
                            ServiceStatus::Draft => __('vendor-portal.services.draft'),
                            ServiceStatus::PendingReview => __('vendor-portal.services.pending_review'),
                            ServiceStatus::Published => __('vendor-portal.services.published'),
                            ServiceStatus::Rejected => __('vendor-portal.services.rejected'),
                            ServiceStatus::ChangesRequested => __('vendor-portal.services.changes_requested'),
                            ServiceStatus::Archived => __('vendor-portal.services.archived'),
                            default => '—',
                        };
                    })
                    ->icon(fn (Service $record): ?string => ServiceStatus::tryFrom($record->status?->getValue() ?? '') === ServiceStatus::Draft
                        ? 'heroicon-o-pencil-square'
                        : null)
                    ->tooltip(fn (Service $record): ?string => ServiceStatus::tryFrom($record->status?->getValue() ?? '') === ServiceStatus::Draft
                        ? __('vendor-portal.services.draft_cue_tooltip')
                        : null),
                TextColumn::make('base_price_minor')
                    ->label(__('catalog.base_price'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('updated_at')
                    ->label(__('vendor-portal.services.last_modified'))
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('product_type')
                    ->label(__('vendor-portal.services.type'))
                    ->options([
                        ProductType::Rental->value => ProductType::Rental->label(),
                        ProductType::Sale->value => ProductType::Sale->label(),
                        ProductType::Digital->value => ProductType::Digital->label(),
                    ]),
                SelectFilter::make('status')
                    ->label(__('vendor-portal.services.status'))
                    ->options([
                        ServiceStatus::Draft->value => __('vendor-portal.services.draft'),
                        ServiceStatus::PendingReview->value => __('vendor-portal.services.pending_review'),
                        ServiceStatus::Published->value => __('vendor-portal.services.published'),
                        ServiceStatus::Rejected->value => __('vendor-portal.services.rejected'),
                    ]),
            ])
            ->actions([
                TableAction::make('edit')
                    ->label(__('catalog.edit'))
                    ->icon('heroicon-o-pencil')
                    ->color('gray')
                    ->url(fn (Service $record): string => match ($record->product_type) {
                        ProductType::Rental => VendorRentalServiceResource::getUrl('edit', ['record' => $record->public_id], panel: 'vendor'),
                        ProductType::Sale => VendorSaleServiceResource::getUrl('edit', ['record' => $record->public_id], panel: 'vendor'),
                        ProductType::Digital => VendorDigitalServiceResource::getUrl('edit', ['record' => $record->public_id], panel: 'vendor'),
                    }),

                TableAction::make('manageAvailability')
                    ->label(__('vendor-portal.availability.manage'))
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->url(fn (Service $record): string => VendorServiceAvailabilityPage::getUrl(['service' => $record->public_id], panel: 'vendor'))
                    ->visible(fn (Service $record): bool => $record->product_type === ProductType::Rental),

                TableAction::make('submit')
                    ->label(__('vendor-portal.services.submit_for_review'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (Service $record): bool => in_array(
                        ServiceStatus::tryFrom($record->status?->getValue() ?? ''),
                        [ServiceStatus::Draft, ServiceStatus::ChangesRequested],
                        true,
                    ))
                    ->action(function (Service $record): void {
                        app(SubmitServiceForReviewAction::class)->execute($record, $this->getVendorProfile());
                        Notification::make()->title(__('vendor-portal.services.submitted'))->success()->send();
                    }),

                TableAction::make('clone')
                    ->label(__('vendor-portal.services.clone'))
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (Service $record): void {
                        $clone = app(CloneServiceAction::class)->execute($record, $this->getVendorProfile());
                        Notification::make()->title(__('vendor-portal.services.cloned'))->success()->send();
                    }),

                TableAction::make('archive')
                    ->label(__('vendor-portal.services.archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Service $record) => $record->status !== ServiceStatus::Archived)
                    ->action(function (Service $record): void {
                        app(VendorArchiveServiceAction::class)->execute($record, $this->getVendorProfile());
                        Notification::make()->title(__('vendor-portal.services.archived'))->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkSubmit')
                        ->label(__('vendor-portal.services.submit_for_review'))
                        ->action(function (Collection $records): void {
                            $profile = $this->getVendorProfile();
                            $action = app(SubmitServiceForReviewAction::class);
                            $count = 0;

                            foreach ($records as $service) {
                                try {
                                    $action->execute($service, $profile);
                                    $count++;
                                } catch (Throwable) {
                                    // Skip invalid transitions
                                }
                            }

                            Notification::make()->title(__('vendor-portal.services.bulk_submitted', ['count' => $count]))->success()->send();
                        }),

                    BulkAction::make('bulkArchive')
                        ->label(__('vendor-portal.services.archive'))
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $profile = $this->getVendorProfile();
                            $action = app(VendorArchiveServiceAction::class);
                            $count = 0;

                            foreach ($records as $service) {
                                if ($service->status === ServiceStatus::Archived) {
                                    continue;
                                }

                                try {
                                    $action->execute($service, $profile);
                                    $count++;
                                } catch (Throwable) {
                                    // Skip services that cannot transition to archived
                                }
                            }

                            Notification::make()->title(__('vendor-portal.services.bulk_archived', ['count' => $count]))->success()->send();
                        }),
                ]),
            ]);
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}

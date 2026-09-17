<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Filament\Resources\VendorCoverageAreaResource\Pages\ListVendorCoverageAreas;
use App\Modules\Shared\Application\Services\StorefrontText;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorCoverageAreaResource extends Resource
{
    protected static ?string $model = VendorCoverageArea::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_onboarding');
    }

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.coverage_areas');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.coverage_area.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.coverage_area.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['vendorProfile.user', 'city.governorate']);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('vendorProfile.public_id')->label(__('identity.columns.vendor_reference'))->searchable()->copyable(),
            TextColumn::make('vendorProfile.business_name')->label(__('identity.columns.vendor'))
                ->getStateUsing(fn (VendorCoverageArea $record): string => $record->vendorProfile === null
                    ? '-'
                    : (app(StorefrontText::class)->translation($record->vendorProfile, 'business_name') ?: '-'))
                ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('vendorProfile', fn (Builder $vendor) => $vendor->where(
                    fn (Builder $match) => $match
                        ->where('public_id', 'like', "%{$search}%")
                        ->orWhere('business_name->en', 'like', "%{$search}%")
                        ->orWhere('business_name->ar', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $user) => $user->where(
                            fn (Builder $contact) => $contact
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone_e164', 'like', "%{$search}%")
                        ))
                ))),
            TextColumn::make('city.governorate.name')->label(__('identity.columns.governorate'))
                ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '-') : ($state ?? '-')),
            TextColumn::make('city.name')->label(__('identity.columns.city'))
                ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '-') : ($state ?? '-'))
                ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('city', fn (Builder $city) => $city->where(
                    fn (Builder $match) => $match
                        ->where('public_id', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%")
                        ->orWhereHas('governorate', fn (Builder $governorate) => $governorate->where(
                            fn (Builder $governorateMatch) => $governorateMatch
                                ->where('public_id', 'like', "%{$search}%")
                                ->orWhere('name->en', 'like', "%{$search}%")
                                ->orWhere('name->ar', 'like', "%{$search}%")
                        ))
                ))),
            TextColumn::make('delivery_fee_minor')->label(__('identity.columns.delivery_fee'))->money('EGP', divideBy: 100),
            TextColumn::make('min_order_minor')->label(__('identity.columns.min_order'))->money('EGP', divideBy: 100),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListVendorCoverageAreas::route('/')];
    }
}

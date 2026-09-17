<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Filament\Resources\VendorApprovedProductTypeResource\Pages\ListVendorApprovedProductTypes;
use App\Modules\Shared\Application\Services\StorefrontText;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorApprovedProductTypeResource extends Resource
{
    protected static ?string $model = VendorApprovedProductType::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_onboarding');
    }

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.approved_product_types');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.approved_product_type.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.approved_product_type.plural');
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
        return parent::getEloquentQuery()->with('vendorProfile.user');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('vendorProfile.public_id')->label(__('identity.columns.vendor_reference'))->searchable()->copyable(),
            TextColumn::make('vendorProfile.business_name')->label(__('identity.columns.vendor'))
                ->getStateUsing(fn (VendorApprovedProductType $record): string => $record->vendorProfile === null
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
            TextColumn::make('product_type')->label(__('identity.columns.product_type'))->badge()->searchable()
                ->formatStateUsing(fn (ProductType $state): string => $state->label()),
            TextColumn::make('approved_at')->label(__('identity.columns.approved_at'))->dateTime(),
            TextColumn::make('revoked_at')->label(__('identity.columns.revoked_at'))->dateTime(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListVendorApprovedProductTypes::route('/')];
    }
}

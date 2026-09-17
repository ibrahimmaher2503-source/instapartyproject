<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Domain\Enums\DayOfWeek;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Filament\Resources\VendorBusinessHourResource\Pages\ListVendorBusinessHours;
use App\Modules\Shared\Application\Services\StorefrontText;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorBusinessHourResource extends Resource
{
    protected static ?string $model = VendorBusinessHour::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_onboarding');
    }

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.business_hours');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.business_hour.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.business_hour.plural');
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
                ->getStateUsing(fn (VendorBusinessHour $record): string => $record->vendorProfile === null
                    ? '-'
                    : (app(StorefrontText::class)->translation($record->vendorProfile, 'business_name') ?: '-'))
                ->searchable(query: fn (Builder $query, string $search) => self::searchVendor($query, $search)),
            TextColumn::make('day_of_week')->label(__('identity.columns.day_of_week'))->badge()
                ->formatStateUsing(fn (DayOfWeek $state): string => __('identity.day_of_week.'.$state->value)),
            TextColumn::make('opens_at')->label(__('identity.columns.opens_at')),
            TextColumn::make('closes_at')->label(__('identity.columns.closes_at')),
        ]);
    }

    private static function searchVendor(Builder $query, string $search): Builder
    {
        return $query->whereHas('vendorProfile', fn (Builder $vendor) => $vendor->where(
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
        ));
    }

    public static function getPages(): array
    {
        return ['index' => ListVendorBusinessHours::route('/')];
    }
}

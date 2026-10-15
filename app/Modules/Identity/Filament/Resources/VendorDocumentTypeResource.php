<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Application\Actions\DeleteVendorDocumentTypeAction;
use App\Modules\Identity\Application\Actions\SaveVendorDocumentTypeAction;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Models\VendorDocumentType;
use App\Modules\Identity\Filament\Resources\VendorDocumentTypeResource\Pages\ManageVendorDocumentTypes;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VendorDocumentTypeResource extends Resource
{
    protected static ?string $model = VendorDocumentType::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_onboarding');
    }

    public static function getModelLabel(): string
    {
        return __('identity::document_types.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity::document_types.title');
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('review_vendor_documents');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('identity::document_types.details'))->schema([
                TextInput::make('name.en')->label(__('identity::document_types.name_en'))->required()->maxLength(255),
                TextInput::make('name.ar')->label(__('identity::document_types.name_ar'))->required()->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                CheckboxList::make('required_for')
                    ->label(__('identity::document_types.required_for'))
                    ->options(collect(BusinessType::cases())->mapWithKeys(fn (BusinessType $type): array => [$type->value => __('identity::identity.business_type.'.$type->value)])->all())
                    ->helperText(__('identity::document_types.required_help'))
                    ->default([])->columns(3)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('identity::identity.columns.doc_type'))->searchable(['name->en', 'name->ar']),
            TextColumn::make('required_for')
                ->label(__('identity::document_types.required_for'))->badge()
                ->formatStateUsing(fn (string $state): string => __('identity::identity.business_type.'.$state))
                ->placeholder(__('identity::document_types.optional')),
        ])->defaultSort('id')->actions([
            EditAction::make()
                ->mutateRecordDataUsing(function (array $data, VendorDocumentType $record): array {
                    $data['name'] = $record->getTranslations('name');

                    return $data;
                })
                ->using(fn (VendorDocumentType $record, array $data): VendorDocumentType => app(SaveVendorDocumentTypeAction::class)->execute($record, $data)),
            DeleteAction::make()
                ->modalDescription(__('identity::document_types.remove_help'))
                ->using(fn (VendorDocumentType $record): bool => app(DeleteVendorDocumentTypeAction::class)->execute($record)),
        ])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVendorDocumentTypes::route('/')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_active', true);
    }
}

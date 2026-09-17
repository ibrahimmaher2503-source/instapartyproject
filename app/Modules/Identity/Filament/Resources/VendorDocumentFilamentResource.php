<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Application\Actions\ApproveVendorDocumentAction;
use App\Modules\Identity\Application\Actions\GenerateDocumentSignedUrlAction;
use App\Modules\Identity\Application\Actions\RejectVendorDocumentAction;
use App\Modules\Identity\Application\Actions\SetDocumentExpiryAction;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages\EditVendorDocument;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages\ListVendorDocuments;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages\ViewVendorDocument;
use App\Modules\Shared\Application\Services\StorefrontText;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VendorDocumentFilamentResource extends Resource
{
    protected static ?string $model = VendorDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_onboarding');
    }

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.documents');
    }

    public static function getModelLabel(): string
    {
        return __('identity.vendor_document');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.vendor_documents');
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Section::make(__('identity.compliance.document_context'))
                ->schema([
                    Placeholder::make('vendor_context')
                        ->label(__('identity.columns.vendor'))
                        ->content(fn (?VendorDocument $record): string => $record?->vendorProfile === null
                            ? '—'
                            : (app(StorefrontText::class)->translation($record->vendorProfile, 'business_name') ?: '—')),
                    Placeholder::make('document_reference')
                        ->label(__('identity.columns.document_reference'))
                        ->content(fn (?VendorDocument $record): string => $record?->public_id ?? '—'),
                    Placeholder::make('document_type')
                        ->label(__('identity.columns.doc_type'))
                        ->content(fn (?VendorDocument $record): string => $record === null ? '—' : __('identity.document_type.'.$record->doc_type->value)),
                    Placeholder::make('file_context')
                        ->label(__('identity.columns.file_name'))
                        ->content(fn (?VendorDocument $record): string => $record?->file_name ?? '—'),
                    Placeholder::make('status_context')
                        ->label(__('identity.columns.status'))
                        ->content(fn (?VendorDocument $record): string => $record === null ? '—' : __('identity.document_status.'.$record->status->value)),
                    Placeholder::make('uploaded_context')
                        ->label(__('identity.fields.uploaded_at'))
                        ->content(fn (?VendorDocument $record): string => $record?->created_at?->format('Y-m-d H:i') ?? '—'),
                    Placeholder::make('reviewer_context')
                        ->label(__('identity.columns.reviewed_by'))
                        ->content(fn (?VendorDocument $record): string => $record?->reviewer?->name ?? '—'),
                    Placeholder::make('reviewed_context')
                        ->label(__('identity.columns.reviewed_at'))
                        ->content(fn (?VendorDocument $record): string => $record?->reviewed_at?->format('Y-m-d H:i') ?? '—'),
                ])
                ->columns(2),
            Section::make(__('identity.compliance.expiry_and_criticality'))
                ->schema([
                    DatePicker::make('expires_at')
                        ->required()
                        ->minDate(today())
                        ->label(__('identity.fields.expires_at')),
                    Toggle::make('is_critical')
                        ->label(__('identity.fields.is_critical')),
                ])->columns(2),
        ]);
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            InfolistSection::make(__('identity.compliance.document_context'))
                ->schema([
                    TextEntry::make('vendorProfile.business_name')
                        ->label(__('identity.columns.vendor'))
                        ->getStateUsing(fn (VendorDocument $record): string => $record->vendorProfile === null
                            ? '—'
                            : (app(StorefrontText::class)->translation($record->vendorProfile, 'business_name') ?: '—')),
                    TextEntry::make('public_id')->label(__('identity.columns.document_reference'))->copyable(),
                    TextEntry::make('doc_type')
                        ->label(__('identity.columns.doc_type'))
                        ->formatStateUsing(fn (BackedEnum|string $state): string => __('identity.document_type.'.($state instanceof BackedEnum ? $state->value : $state))),
                    TextEntry::make('file_name')->label(__('identity.columns.file_name')),
                    TextEntry::make('status')
                        ->label(__('identity.columns.status'))
                        ->badge()
                        ->formatStateUsing(fn (BackedEnum|string $state): string => __('identity.document_status.'.($state instanceof BackedEnum ? $state->value : $state))),
                    TextEntry::make('created_at')->label(__('identity.fields.uploaded_at'))->dateTime(),
                    TextEntry::make('reviewer.name')->label(__('identity.columns.reviewed_by'))->placeholder('—'),
                    TextEntry::make('reviewed_at')->label(__('identity.columns.reviewed_at'))->dateTime()->placeholder('—'),
                    TextEntry::make('expires_at')->label(__('identity.fields.expires_at'))->date()->placeholder('—'),
                    TextEntry::make('is_critical')
                        ->label(__('identity.fields.is_critical'))
                        ->formatStateUsing(fn (bool $state): string => $state ? __('identity.misc.yes') : __('identity.misc.no')),
                    TextEntry::make('review_notes')
                        ->label(__('identity.columns.review_notes'))
                        ->getStateUsing(fn (VendorDocument $record): string => $record->getTranslation('review_notes', app()->getLocale(), false) ?: '—')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['vendorProfile.user', 'reviewer']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('identity.columns.document_reference'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('vendorProfile.public_id')
                    ->label(__('identity.columns.vendor_reference'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('vendorProfile.business_name')
                    ->label(__('identity.columns.vendor'))
                    ->getStateUsing(fn (VendorDocument $record): string => $record->vendorProfile === null
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
                TextColumn::make('doc_type')
                    ->label(__('identity.columns.doc_type'))
                    ->badge()
                    ->searchable()
                    ->formatStateUsing(fn ($state): string => __('identity.document_type.'.($state instanceof BackedEnum ? $state->value : $state))),
                TextColumn::make('file_name')
                    ->label(__('identity.columns.file_name'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('identity.columns.status'))
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof BackedEnum ? $state->value : $state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => __('identity.document_status.'.($state instanceof BackedEnum ? $state->value : $state))),
                TextColumn::make('expires_at')
                    ->label(__('identity.fields.expires_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('is_critical')
                    ->label(__('identity.fields.is_critical'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('identity.misc.yes') : __('identity.misc.no')),
                TextColumn::make('reviewer.name')
                    ->label(__('identity.columns.reviewed_by'))
                    ->toggleable(),
                TextColumn::make('reviewed_at')
                    ->label(__('identity.columns.reviewed_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('review_notes')
                    ->label(__('identity.columns.review_notes'))
                    ->getStateUsing(fn (VendorDocument $record): string => $record->getTranslation('review_notes', app()->getLocale(), false) ?: '-')
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('identity.columns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (VendorDocument $record): string => static::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('identity.columns.status'))
                    ->options([
                        'pending' => __('identity.document_status.pending'),
                        'approved' => __('identity.document_status.approved'),
                        'rejected' => __('identity.document_status.rejected'),
                    ]),
                SelectFilter::make('doc_type')
                    ->label(__('identity.columns.doc_type'))
                    ->options([
                        'cr' => __('identity.document_type.cr'),
                        'tax_card' => __('identity.document_type.tax_card'),
                        'national_id' => __('identity.document_type.national_id'),
                        'iban_proof' => __('identity.document_type.iban_proof'),
                        'other' => __('identity.document_type.other'),
                    ]),
            ])
            ->actions([
                Action::make('download')
                    ->label(__('identity.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (VendorDocument $record) => redirect()->away(
                        app(GenerateDocumentSignedUrlAction::class)->execute($record, auth()->user())
                    )),
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->can('review_vendor_documents'))
                    ->using(function (VendorDocument $record, array $data) {
                        app(SetDocumentExpiryAction::class)->execute(
                            $record,
                            Carbon::parse($data['expires_at']),
                            (bool) ($data['is_critical'] ?? false)
                        );
                        Notification::make()
                            ->title(__('identity.notifications.expiry_set_successfully'))
                            ->success()
                            ->send();
                    }),

                Action::make('approve')
                    ->label(__('identity.actions.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VendorDocument $record): bool => $record->status === DocumentStatus::Pending
                        && auth()->user()?->can('review_vendor_documents'))
                    ->action(function (VendorDocument $record): void {
                        app(ApproveVendorDocumentAction::class)->execute($record);
                        Notification::make()
                            ->title(__('identity.notifications.document_approved'))
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label(__('identity.actions.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (VendorDocument $record): bool => $record->status === DocumentStatus::Pending
                        && auth()->user()?->can('review_vendor_documents'))
                    ->form([
                        Textarea::make('review_notes_en')
                            ->label(__('identity.forms.rejection_reason_en'))
                            ->required()
                            ->maxLength(1000),
                        Textarea::make('review_notes_ar')
                            ->label(__('identity.forms.rejection_reason_ar'))
                            ->maxLength(1000)
                            ->extraInputAttributes(['dir' => 'rtl']),
                    ])
                    ->action(function (VendorDocument $record, array $data): void {
                        $notes = array_filter([
                            'en' => $data['review_notes_en'] ?? null,
                            'ar' => $data['review_notes_ar'] ?? null,
                        ]);
                        app(RejectVendorDocumentAction::class)->execute($record, $notes);
                        Notification::make()
                            ->title(__('identity.notifications.document_rejected'))
                            ->danger()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorDocuments::route('/'),
            'view' => ViewVendorDocument::route('/{record}'),
            'edit' => EditVendorDocument::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

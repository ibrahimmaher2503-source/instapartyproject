<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Application\Actions\ApproveVendorDocumentAction;
use App\Modules\Identity\Application\Actions\ApproveVendorProfileAction;
use App\Modules\Identity\Application\Actions\GenerateDocumentSignedUrlAction;
use App\Modules\Identity\Application\Actions\RejectVendorProfileAction;
use App\Modules\Identity\Application\Services\RequiredVendorDocumentTypesResolver;
use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Application\Services\VendorSearchQuery;
use App\Modules\Identity\Application\Support\MaskBankData;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages\ListVendorApprovalQueue;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages\ReviewVendorApplication;
use App\Modules\Shared\Application\Services\StorefrontText;
use App\Modules\Shared\Filament\Tables\OperationalDateFilter;
use App\Modules\Shared\Filament\Tables\OperationalExport;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\Actions\Action as InlineAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\ModelStates\State;
use Throwable;

class VendorApprovalQueueResource extends Resource
{
    protected static ?string $model = VendorProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $slug = 'vendor-approval-queue';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.queue');
    }

    public static function getModelLabel(): string
    {
        return __('identity.vendor_profile');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.vendor_profiles');
    }

    /**
     * @return Builder<VendorProfile>
     */
    public static function getEloquentQuery(): Builder
    {
        return VendorProfile::query()
            ->whereIn('approval_status', ['pending', 'changes_requested']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([OperationalExport::make(static::class, ['public_id', 'approval_status', 'created_at'])])
            ->columns([
                TextColumn::make('business_name')
                    ->label(__('identity.columns.business_name'))
                    ->getStateUsing(fn (VendorProfile $record): string => app(StorefrontText::class)->translation($record, 'business_name') ?: '')
                    ->searchable(query: fn (Builder $query, string $search): Builder => app(VendorSearchQuery::class)->apply($query, $search))
                    ->description(function (VendorProfile $record): ?string {
                        $result = app(VendorApprovalEligibilityService::class)->evaluate($record);

                        return $result->eligible ? null : __('identity.approval_eligibility.blocked_summary', [
                            'requirements' => collect(array_keys($result->errors))
                                ->map(fn (string $check): string => __('identity.approval_eligibility.requirements.'.$check))
                                ->implode('، '),
                        ]);
                    })
                    ->wrap()
                    ->limit(40),
                TextColumn::make('business_type')
                    ->label(__('identity.columns.business_type'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => __('identity.business_type.'.(
                        $state instanceof BackedEnum ? $state->value : (string) $state
                    ))),
                TextColumn::make('user.email')
                    ->label(__('identity.columns.email')),
                TextColumn::make('documents_count')
                    ->label(__('identity.columns.docs'))
                    ->counts('documents')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('identity.columns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->filters([
                OperationalDateFilter::make('created_at'),
                SelectFilter::make('business_type')
                    ->label(__('identity.columns.business_type'))
                    ->options(collect(BusinessType::cases())->mapWithKeys(fn (BusinessType $t) => [$t->value => __('identity.business_type.'.$t->value)])->all()),
            ])
            ->actions([
                ViewAction::make()
                    ->label(__('identity.actions.review'))
                    ->icon('heroicon-o-eye')
                    ->color('primary'),

                Action::make('approvalRequirements')
                    ->label(__('identity.approval_eligibility.blocked_title'))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->visible(fn (VendorProfile $record): bool => ! app(VendorApprovalEligibilityService::class)->evaluate($record)->eligible)
                    ->modalHeading(__('identity.approval_eligibility.blocked_title'))
                    ->modalDescription(__('identity.approval_eligibility.blocked_description'))
                    ->modalContent(fn (VendorProfile $record) => view('filament.partials.vendor-approval-requirements', [
                        'errors' => app(VendorApprovalEligibilityService::class)->evaluate($record)->errors,
                    ]))
                    ->modalSubmitAction(false),

                Action::make('approve')
                    ->label(__('identity.actions.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VendorProfile $record): bool => $record->approval_status instanceof PendingState
                        && (bool) auth()->user()?->can('approve_vendor_profile'))
                    ->disabled(fn (VendorProfile $record): bool => ! app(VendorApprovalEligibilityService::class)->evaluate($record)->eligible)
                    ->tooltip(fn (VendorProfile $record): ?string => app(VendorApprovalEligibilityService::class)->evaluate($record)->eligible
                        ? null
                        : (string) __('identity.approval_eligibility.complete_requirements'))
                    ->action(function (VendorProfile $record): void {
                        app(ApproveVendorProfileAction::class)->execute($record);
                        Notification::make()->title(__('identity.notifications.vendor_approved'))->success()->send();
                    }),

                Action::make('reject')
                    ->label(__('identity.actions.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (VendorProfile $record): bool => $record->approval_status instanceof PendingState
                        && (bool) auth()->user()?->can('reject_vendor_profile'))
                    ->form([
                        Textarea::make('rejection_reason_en')
                            ->label(__('identity.forms.rejection_reason_en'))
                            ->rules(['required'])
                            ->validationAttribute(__('identity.forms.rejection_reason'))
                            ->maxLength(1000),
                        Textarea::make('rejection_reason_ar')
                            ->label(__('identity.forms.rejection_reason_ar'))
                            ->maxLength(1000),
                    ])
                    ->action(function (VendorProfile $record, array $data): void {
                        $reason = array_filter([
                            'en' => $data['rejection_reason_en'] ?? null,
                            'ar' => $data['rejection_reason_ar'] ?? null,
                        ]);
                        app(RejectVendorProfileAction::class)->execute($record, $reason);
                        Notification::make()->title(__('identity.notifications.vendor_rejected'))->danger()->send();
                    }),
            ])
            ->recordUrl(fn (VendorProfile $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('admin.empty_states.no_pending_vendors'))
            ->emptyStateDescription(__('admin.empty_states.no_pending_vendors_description'));
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            InfolistSection::make(__('identity.approval_eligibility.blocked_title'))
                ->icon('heroicon-o-exclamation-triangle')
                ->description(__('identity.approval_eligibility.blocked_description'))
                ->visible(fn (VendorProfile $record): bool => ! app(VendorApprovalEligibilityService::class)->evaluate($record)->eligible)
                ->schema([
                    TextEntry::make('approval_blockers')
                        ->hiddenLabel()
                        ->state(fn (VendorProfile $record): array => collect(array_keys(app(VendorApprovalEligibilityService::class)->evaluate($record)->errors))
                            ->map(fn (string $check): string => __('identity.approval_eligibility.requirements.'.$check))
                            ->all())
                        ->listWithLineBreaks()
                        ->bulleted(),
                ]),
            InfolistSection::make(__('identity.sections.identity'))
                ->columns(2)
                ->schema([
                    TextEntry::make('public_id')->label(__('identity.columns.id'))->copyable(),
                    TextEntry::make('user.email')->label(__('identity.columns.email')),
                    TextEntry::make('user.phone_e164')
                        ->label(__('identity.columns.phone'))
                        ->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make('user.phone_verified_at')
                        ->label(__('identity.columns.phone_verified'))
                        ->dateTime()
                        ->placeholder(__('identity.placeholders.not_verified')),
                    TextEntry::make('business_type')
                        ->label(__('identity.columns.business_type'))
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => __('identity.business_type.'.(
                            $state instanceof BackedEnum ? $state->value : (string) $state
                        ))),
                    TextEntry::make('approval_status')
                        ->label(__('identity.columns.approval_status'))
                        ->badge()
                        ->color('warning')
                        ->formatStateUsing(function (mixed $state): string {
                            $value = $state instanceof State
                                ? $state::getMorphClass()
                                : ($state instanceof BackedEnum ? $state->value : (string) $state);

                            return __('identity.status.'.$value);
                        }),
                ]),

            InfolistSection::make(__('identity.sections.business_profile'))
                ->columns(2)
                ->schema([
                    TextEntry::make('business_name_en')
                        ->label(__('identity.forms.business_name_en'))
                        ->getStateUsing(fn (VendorProfile $record): ?string => app(StorefrontText::class)->translation($record, 'business_name', 'en') ?: null),
                    TextEntry::make('business_name_ar')
                        ->label(__('identity.forms.business_name_ar'))
                        ->getStateUsing(fn (VendorProfile $record): ?string => app(StorefrontText::class)->translation($record, 'business_name', 'ar') ?: null),
                    TextEntry::make('slug')->label(__('identity.columns.slug')),
                    TextEntry::make('primaryGovernorate.name')
                        ->label(__('identity.columns.governorate'))
                        ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—')),
                    TextEntry::make('primaryCity.name')
                        ->label(__('identity.columns.city'))
                        ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—')),
                    TextEntry::make('created_at')->label(__('identity.columns.created_at'))->dateTime(),
                    TextEntry::make('bio_en')
                        ->label(__('identity.forms.bio_en'))
                        ->getStateUsing(fn (VendorProfile $record): ?string => $record->getTranslation('bio', 'en', false) ?: null)
                        ->placeholder(__('identity.placeholders.dash'))
                        ->columnSpanFull(),
                ]),

            InfolistSection::make(__('identity.sections.banking'))
                ->columns(2)
                ->schema([
                    TextEntry::make('bank_name')
                        ->label(__('identity.columns.bank_name'))
                        ->state(fn (VendorProfile $record): string => MaskBankData::label($record->bank_name))
                        ->placeholder(__('identity.placeholders.dash')),
                    TextEntry::make('bank_account_holder')
                        ->label(__('identity.columns.bank_account_holder'))
                        ->state(fn (VendorProfile $record): string => MaskBankData::label($record->bank_account_holder))
                        ->placeholder(__('identity.placeholders.dash')),
                    TextEntry::make('bank_iban')
                        ->label(__('identity.columns.bank_iban'))
                        ->state(fn (VendorProfile $record): string => MaskBankData::iban($record->bank_iban))
                        ->placeholder(__('identity.placeholders.dash')),
                    TextEntry::make('bank_swift_bic')
                        ->label(__('identity.columns.bank_swift_bic'))
                        ->state(fn (VendorProfile $record): string => MaskBankData::label($record->bank_swift_bic))
                        ->placeholder(__('identity.placeholders.dash')),
                ]),

            InfolistSection::make(__('identity.approval_eligibility.checklist'))
                ->description(__('identity.approval_eligibility.checklist_description'))
                ->schema([
                    RepeatableEntry::make('approval_eligibility_checklist')
                        ->hiddenLabel()
                        ->state(function (VendorProfile $record): array {
                            $result = app(VendorApprovalEligibilityService::class)->evaluate($record);

                            return collect($result->checks)
                                ->map(fn (bool $passed, string $check): array => [
                                    'requirement' => __('identity.approval_eligibility.requirements.'.$check),
                                    'status' => $passed
                                        ? __('identity.approval_eligibility.complete')
                                        : __('identity.approval_eligibility.incomplete'),
                                    'passed' => $passed,
                                ])
                                ->values()
                                ->all();
                        })
                        ->columns(2)
                        ->schema([
                            TextEntry::make('requirement')->hiddenLabel(),
                            TextEntry::make('status')
                                ->hiddenLabel()
                                ->badge()
                                ->color(fn (string $state): string => $state === __('identity.approval_eligibility.complete') ? 'success' : 'danger'),
                        ]),
                ]),

            InfolistSection::make(__('identity.sections.uploaded_documents'))
                ->schema([
                    RepeatableEntry::make('documents')
                        ->columns(4)
                        ->schema([
                            TextEntry::make('doc_type')
                                ->label(__('identity.columns.doc_type'))
                                ->badge()
                                ->formatStateUsing(fn (VendorDocument $record): string => app(RequiredVendorDocumentTypesResolver::class)->label($record->doc_type_key)),
                            TextEntry::make('file_name')
                                ->label(__('identity.columns.file_name'))
                                ->hintActions([
                                    InlineAction::make('reviewDocument')
                                        ->label(__('identity.actions.review'))
                                        ->icon('heroicon-o-eye')
                                        ->visible(fn (VendorDocument $record): bool => VendorDocumentFilamentResource::canView($record))
                                        ->url(fn (VendorDocument $record): string => VendorDocumentFilamentResource::getUrl('view', ['record' => $record])),
                                    InlineAction::make('approveDocument')
                                        ->label(__('identity.actions.approve'))
                                        ->icon('heroicon-o-check-circle')
                                        ->color('success')
                                        ->requiresConfirmation()
                                        ->visible(fn (VendorDocument $record): bool => $record->status === DocumentStatus::Pending
                                            && (bool) auth()->user()?->can('review_vendor_documents'))
                                        ->action(function (VendorDocument $record, ViewRecord $livewire): void {
                                            app(ApproveVendorDocumentAction::class)->execute($record);
                                            $livewire->getRecord()->refresh();
                                            Notification::make()->title(__('identity.notifications.document_approved'))->success()->send();
                                        }),
                                ]),
                            TextEntry::make('status')
                                ->label(__('identity.columns.status'))
                                ->badge()
                                ->formatStateUsing(fn (mixed $state): string => __('identity.document_status.'.(
                                    $state instanceof BackedEnum ? $state->value : (string) $state
                                ))),
                            TextEntry::make('signed_url')
                                ->label(__('identity.columns.download'))
                                ->getStateUsing(function (VendorDocument $record): string {
                                    $user = auth()->user();

                                    if (! $user instanceof User) {
                                        return '#';
                                    }

                                    try {
                                        return app(GenerateDocumentSignedUrlAction::class)->execute($record, $user);
                                    } catch (Throwable) {
                                        return '#';
                                    }
                                })
                                ->url(fn (string $state): string => $state, shouldOpenInNewTab: true)
                                ->formatStateUsing(fn (string $state): string => $state === '#' ? __('identity.placeholders.s3_not_configured') : __('identity.placeholders.open_document')),
                        ])
                        ->placeholder(__('identity.placeholders.no_documents')),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorApprovalQueue::route('/'),
            'view' => ReviewVendorApplication::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

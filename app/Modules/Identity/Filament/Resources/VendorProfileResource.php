<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Application\Actions\ApproveVendorForTypeAction;
use App\Modules\Identity\Application\Actions\ApproveVendorProfileAction;
use App\Modules\Identity\Application\Actions\RejectVendorProfileAction;
use App\Modules\Identity\Application\Actions\RequestVendorChangesAction;
use App\Modules\Identity\Application\Actions\RevokeVendorTypeAction;
use App\Modules\Identity\Application\Actions\SuspendVendorAction;
use App\Modules\Identity\Application\Actions\UnsuspendVendorAction;
use App\Modules\Identity\Application\Services\VendorSearchQuery;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Enums\DayOfWeek;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages\EditVendorProfile;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages\ListVendorProfiles;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages\ViewVendorProfile;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers\BookingVendorsRelationManager;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers\DocumentsRelationManager;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers\ServicesRelationManager;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers\VendorReviewsRelationManager;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers\WalletRelationManager;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers\WithdrawalsRelationManager;
use App\Modules\Shared\Application\Services\StorefrontText;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Filament\Infolists\Components\AuditTimelineSection;
use App\Modules\TrustSafety\Application\Actions\AssignBadgeToVendorAction;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\State;

class VendorProfileResource extends Resource
{
    protected static ?string $model = VendorProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.all_vendors');
    }

    public static function getModelLabel(): string
    {
        return __('identity.vendor_profile');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.vendor_profiles');
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Tabs::make(__('identity.misc.translations_tab'))
                ->tabs([
                    Tab::make(__('identity.misc.tab_english'))
                        ->schema([
                            TextInput::make('business_name.en')
                                ->label(__('identity.forms.business_name_en'))
                                ->required()
                                ->maxLength(255),
                            Textarea::make('bio.en')
                                ->label(__('identity.forms.bio_en'))
                                ->rows(3)
                                ->maxLength(2000),
                            Textarea::make('address_line.en')
                                ->label(__('identity.fields.address_line').' (EN)')
                                ->rows(2)
                                ->maxLength(500),
                        ]),
                    Tab::make(__('identity.misc.tab_arabic'))
                        ->schema([
                            TextInput::make('business_name.ar')
                                ->label(__('identity.forms.business_name_ar'))
                                ->required()
                                ->maxLength(255)
                                ->extraInputAttributes(['dir' => 'rtl']),
                            Textarea::make('bio.ar')
                                ->label(__('identity.forms.bio_ar'))
                                ->rows(3)
                                ->maxLength(2000)
                                ->extraInputAttributes(['dir' => 'rtl']),
                            Textarea::make('address_line.ar')
                                ->label(__('identity.fields.address_line').' (AR)')
                                ->rows(2)
                                ->maxLength(500)
                                ->extraInputAttributes(['dir' => 'rtl']),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make(__('identity.sections.business_profile'))
                ->columns(2)
                ->schema([
                    Select::make('business_type')
                        ->label(__('identity.fields.business_type'))
                        ->options([
                            BusinessType::Individual->value => __('identity.business_type.individual'),
                            BusinessType::Company->value => __('identity.business_type.company'),
                        ])
                        ->required(),
                    TextInput::make('commercial_register_no')
                        ->label(__('identity.fields.commercial_register_no'))
                        ->maxLength(60),
                    TextInput::make('tax_id')
                        ->label(__('identity.fields.tax_id'))
                        ->maxLength(60),
                    TextInput::make('national_id')
                        ->label(__('identity.fields.national_id'))
                        ->maxLength(20),
                    Select::make('primary_governorate_id')
                        ->label(__('identity.columns.governorate'))
                        ->options(fn () => DB::table('governorates')->pluck('name', 'id')
                            ->mapWithKeys(fn ($name, $id) => [$id => is_string($name) ? (json_decode($name, true)['en'] ?? $name) : $name])
                            ->toArray())
                        ->searchable()
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn (callable $set) => $set('primary_city_id', null)),
                    Select::make('primary_city_id')
                        ->label(__('identity.columns.city'))
                        ->options(fn (callable $get) => DB::table('cities')
                            ->when($get('primary_governorate_id'), fn ($q, $gov) => $q->where('governorate_id', $gov))
                            ->get()
                            ->mapWithKeys(fn ($city) => [
                                $city->id => is_string($city->name) ? (json_decode($city->name, true)['en'] ?? $city->name) : $city->name,
                            ])
                            ->toArray())
                        ->searchable()
                        ->required(),
                ]),

            Section::make(__('identity.sections.banking'))
                ->columns(2)
                ->schema([
                    TextInput::make('bank_account_holder')
                        ->label(__('identity.columns.bank_account_holder'))
                        ->maxLength(160)
                        ->extraInputAttributes(['autocomplete' => 'off']),
                    TextInput::make('bank_iban')
                        ->label(__('identity.columns.bank_iban'))
                        ->maxLength(34)
                        ->extraInputAttributes(['autocomplete' => 'off']),
                    TextInput::make('bank_swift_bic')
                        ->label(__('identity.columns.bank_swift_bic'))
                        ->maxLength(11)
                        ->extraInputAttributes(['autocomplete' => 'off']),
                    TextInput::make('bank_name')
                        ->label(__('identity.columns.bank_name'))
                        ->maxLength(120)
                        ->extraInputAttributes(['autocomplete' => 'off']),
                    TextInput::make('bank_branch')
                        ->label(__('identity.columns.bank_branch'))
                        ->maxLength(120)
                        ->extraInputAttributes(['autocomplete' => 'off']),
                ]),

            Section::make(__('identity.sections.business_hours'))
                ->schema([
                    Repeater::make('business_hours')
                        ->label('')
                        ->schema([
                            Select::make('day_of_week')
                                ->label(__('identity.columns.day_of_week'))
                                ->options(array_map(
                                    fn (DayOfWeek $d) => $d->label(),
                                    DayOfWeek::cases(),
                                ))
                                ->required(),
                            TimePicker::make('opens_at')
                                ->label(__('identity.columns.opens_at'))
                                ->seconds(false)
                                ->nullable(),
                            TimePicker::make('closes_at')
                                ->label(__('identity.columns.closes_at'))
                                ->seconds(false)
                                ->nullable(),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->collapsible(),
                ])
                ->collapsible(),

            Section::make(__('identity.sections.coverage_areas'))
                ->schema([
                    Repeater::make('coverage_areas')
                        ->label('')
                        ->schema([
                            Select::make('city_id')
                                ->label(__('identity.columns.city'))
                                ->options(fn () => DB::table('cities')
                                    ->get()
                                    ->mapWithKeys(fn ($city) => [
                                        $city->id => is_string($city->name)
                                            ? (json_decode($city->name, true)['en'] ?? $city->name)
                                            : $city->name,
                                    ])
                                    ->toArray())
                                ->searchable()
                                ->required(),
                            TextInput::make('delivery_fee_minor')
                                ->label(__('identity.columns.delivery_fee'))
                                ->numeric()
                                ->suffix(__('identity.misc.piastres'))
                                ->default(0),
                            TextInput::make('min_order_minor')
                                ->label(__('identity.columns.min_order'))
                                ->numeric()
                                ->suffix(__('identity.misc.piastres'))
                                ->default(0),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->collapsible(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('identity.columns.id'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => app(VendorSearchQuery::class)->apply($query, $search))
                    ->copyable(),
                TextColumn::make('business_name')
                    ->label(__('identity.columns.business_name'))
                    ->getStateUsing(fn (VendorProfile $record): string => app(StorefrontText::class)->translation($record, 'business_name') ?: '')
                    ->limit(40),
                TextColumn::make('approval_status')
                    ->label(__('identity.columns.approval_status'))
                    ->badge()
                    ->color(function (mixed $state): string {
                        $value = $state instanceof State ? $state::getMorphClass() : (string) $state;

                        return match ($value) {
                            'pending' => 'warning',
                            'approved' => 'success',
                            'changes_requested' => 'warning',
                            'rejected', 'suspended' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(fn (mixed $state): string => __('identity.status.'.(
                        $state instanceof State ? $state::getMorphClass() : (string) $state
                    )))
                    ->sortable(),
                TextColumn::make('business_type')
                    ->label(__('identity.columns.business_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => __('identity.business_type.'.($state instanceof BackedEnum ? $state->value : $state))),
                TextColumn::make('created_at')
                    ->label(__('identity.columns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('approval_status')
                    ->label(__('identity.columns.approval_status'))
                    ->options([
                        'pending' => __('identity.status.pending'),
                        'approved' => __('identity.status.approved'),
                        'rejected' => __('identity.status.rejected'),
                        'suspended' => __('identity.status.suspended'),
                    ]),
                SelectFilter::make('primary_governorate_id')
                    ->label(__('identity.columns.governorate'))
                    ->options(fn () => DB::table('governorates')
                        ->get()
                        ->mapWithKeys(fn ($gov) => [
                            $gov->id => is_string($gov->name)
                                ? (json_decode($gov->name, true)['en'] ?? $gov->name)
                                : $gov->name,
                        ])
                        ->toArray())
                    ->searchable(),
                Filter::make('has_type_approval')
                    ->label(__('identity.filters.approved_product_type'))
                    ->form([
                        Select::make('product_type')
                            ->label(__('identity.columns.product_type'))
                            ->options([
                                'rental' => __('identity.product_type.rental'),
                                'sale' => __('identity.product_type.sale'),
                                'digital' => __('identity.product_type.digital'),
                            ]),
                    ])
                    ->query(function ($query, array $data): void {
                        if (! empty($data['product_type'])) {
                            $query->whereHas('approvedTypes', fn ($q) => $q->where('product_type', $data['product_type']));
                        }
                    }),
            ])
            ->recordUrl(fn (VendorProfile $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                Action::make('approve')
                    ->label(__('identity.actions.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('identity.modals.approve_vendor_heading'))
                    ->modalDescription(__('identity.modals.approve_vendor_description'))
                    ->visible(fn (VendorProfile $record): bool => $record->approval_status instanceof PendingState
                        && auth()->user()?->can('approve_vendor_profile'))
                    ->action(function (VendorProfile $record): void {
                        app(ApproveVendorProfileAction::class)->execute($record);
                        Notification::make()
                            ->title(__('identity.notifications.vendor_approved'))
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label(__('identity.actions.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (VendorProfile $record): bool => $record->approval_status instanceof PendingState
                        && auth()->user()?->can('reject_vendor_profile'))
                    ->form([
                        Textarea::make('rejection_reason_en')
                            ->label(__('identity.forms.rejection_reason_en'))
                            ->required()
                            ->maxLength(1000),
                        Textarea::make('rejection_reason_ar')
                            ->label(__('identity.forms.rejection_reason_ar'))
                            ->maxLength(1000)
                            ->extraInputAttributes(['dir' => 'rtl']),
                    ])
                    ->action(function (VendorProfile $record, array $data): void {
                        $reason = array_filter([
                            'en' => $data['rejection_reason_en'] ?? null,
                            'ar' => $data['rejection_reason_ar'] ?? null,
                        ]);
                        app(RejectVendorProfileAction::class)->execute($record, $reason);
                        Notification::make()
                            ->title(__('identity.notifications.vendor_rejected'))
                            ->danger()
                            ->send();
                    }),

                Action::make('approveForType')
                    ->label(__('identity.actions.approve_for_type'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (VendorProfile $record) => $record->approval_status instanceof ApprovedState && auth()->user()?->can('approve_vendor_for_type'))
                    ->form([
                        Select::make('product_type')
                            ->label(__('identity.fields.product_type'))
                            ->options([
                                'rental' => __('identity.product_type.rental'),
                                'sale' => __('identity.product_type.sale'),
                                'digital' => __('identity.product_type.digital'),
                            ])
                            ->required(),
                    ])
                    ->action(function (VendorProfile $record, array $data): void {
                        app(ApproveVendorForTypeAction::class)->execute($record, ProductType::from($data['product_type']));
                        Notification::make()
                            ->title(__('identity.notifications.type_approved', [
                                'type' => __('identity.product_type.'.$data['product_type']),
                            ]))
                            ->success()
                            ->send();
                    }),

                Action::make('revokeType')
                    ->label(__('identity.actions.revoke_type'))
                    ->icon('heroicon-o-minus-circle')
                    ->color('warning')
                    ->visible(fn (VendorProfile $record) => $record->approvedTypes()->exists() && auth()->user()?->can('revoke_vendor_type'))
                    ->form([
                        Select::make('product_type')
                            ->label(__('identity.fields.product_type'))
                            ->options([
                                'rental' => __('identity.product_type.rental'),
                                'sale' => __('identity.product_type.sale'),
                                'digital' => __('identity.product_type.digital'),
                            ])
                            ->required(),
                        Textarea::make('revoke_reason_en')
                            ->label(__('identity.forms.revoke_reason_en'))
                            ->required()
                            ->maxLength(1000),
                        Textarea::make('revoke_reason_ar')
                            ->label(__('identity.forms.revoke_reason_ar'))
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (VendorProfile $record, array $data): void {
                        $reason = array_filter([
                            'en' => $data['revoke_reason_en'] ?? null,
                            'ar' => $data['revoke_reason_ar'] ?? null,
                        ]);
                        app(RevokeVendorTypeAction::class)->execute($record, ProductType::from($data['product_type']), $reason);
                        Notification::make()->title(__('identity.notifications.type_revoked'))->warning()->send();
                    }),

                Action::make('suspend')
                    ->label(__('identity.actions.suspend'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VendorProfile $record) => $record->approval_status instanceof ApprovedState && auth()->user()?->can('suspend_vendor'))
                    ->action(function (VendorProfile $record): void {
                        app(SuspendVendorAction::class)->execute($record);
                        Notification::make()->title(__('identity.notifications.vendor_suspended'))->danger()->send();
                    }),

                Action::make('unsuspend')
                    ->label(__('identity.actions.unsuspend'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VendorProfile $record) => $record->approval_status instanceof SuspendedState && auth()->user()?->can('suspend_vendor'))
                    ->action(function (VendorProfile $record): void {
                        app(UnsuspendVendorAction::class)->execute($record);
                        Notification::make()->title(__('identity.notifications.vendor_unsuspended'))->success()->send();
                    }),

                Action::make('requestChanges')
                    ->label(__('identity.actions.request_changes'))
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->visible(fn (VendorProfile $record) => $record->approval_status instanceof PendingState && auth()->user()?->can('manage_vendor_profile'))
                    ->form([
                        Repeater::make('items')
                            ->label('')
                            ->schema([
                                TextInput::make('field_path')
                                    ->label(__('identity.fields.field_path'))
                                    ->required()
                                    ->maxLength(255),
                                Textarea::make('requested_change_en')
                                    ->label(__('identity.fields.requested_change_en'))
                                    ->required()
                                    ->minLength(5)
                                    ->maxLength(1000)
                                    ->rows(2),
                                Textarea::make('requested_change_ar')
                                    ->label(__('identity.fields.requested_change_ar'))
                                    ->required()
                                    ->minLength(5)
                                    ->maxLength(1000)
                                    ->rows(2)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                            ])
                            ->columns(1)
                            ->minItems(1)
                            ->reorderable(false)
                            ->collapsible(),
                    ])
                    ->action(function (VendorProfile $record, array $data): void {
                        app(RequestVendorChangesAction::class)->execute(
                            $record,
                            $data['items'],
                            auth()->user(),
                            'filament:vendor-change:'.auth()->id().':'.$record->public_id.':'.hash('sha256', json_encode($data['items'], JSON_THROW_ON_ERROR)),
                        );
                        Notification::make()
                            ->title(__('identity.notifications.change_request_created'))
                            ->success()
                            ->send();
                    }),

                Action::make('manageBadges')
                    ->label('Manage Badges')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->form([
                        Select::make('badge_ids')
                            ->label('Trust Badges')
                            ->options(fn () => DB::table('trust_badges')
                                ->where('is_active', true)
                                ->pluck('name', 'id')
                                ->map(fn ($name) => json_decode($name, true)['en'] ?? $name)
                                ->toArray())
                            ->multiple()
                            ->preload(),
                    ])
                    ->fillForm(fn (VendorProfile $record): array => [
                        'badge_ids' => DB::table('vendor_badge_assignments')
                            ->where('vendor_profile_id', $record->id)
                            ->pluck('trust_badge_id')
                            ->toArray(),
                    ])
                    ->action(function (VendorProfile $record, array $data): void {
                        $assignAction = app(AssignBadgeToVendorAction::class);
                        $existingIds = DB::table('vendor_badge_assignments')
                            ->where('vendor_profile_id', $record->id)
                            ->pluck('trust_badge_id')
                            ->toArray();
                        $newIds = $data['badge_ids'] ?? [];

                        foreach (array_diff($newIds, $existingIds) as $badgeId) {
                            $assignAction->execute($record->id, (int) $badgeId, auth()->id());
                        }
                        foreach (array_diff($existingIds, $newIds) as $badgeId) {
                            DB::table('vendor_badge_assignments')
                                ->where('vendor_profile_id', $record->id)
                                ->where('trust_badge_id', $badgeId)
                                ->delete();
                        }

                        Notification::make()->title('Badges updated')->success()->send();
                    })
                    ->visible(fn () => auth()->user()->can('update_vendor_profile')),

                EditAction::make(),
                ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            InfolistSection::make(__('identity.sections.identity'))
                ->columns(2)
                ->schema([
                    ImageEntry::make('logo_path')
                        ->label(__('identity.fields.logo'))
                        ->disk('public')
                        ->height(80)
                        ->columnSpanFull()
                        ->visible(fn (VendorProfile $record): bool => (bool) $record->logo_path),
                    TextEntry::make('public_id')->label(__('identity.columns.id'))->copyable(),
                    TextEntry::make('user.email')->label(__('identity.columns.email')),
                    TextEntry::make('user.phone_e164')->label(__('identity.columns.phone')),
                    TextEntry::make('business_type')
                        ->label(__('identity.columns.business_type'))
                        ->badge()
                        ->formatStateUsing(fn ($state): string => __('identity.business_type.'.($state instanceof BackedEnum ? $state->value : $state))),
                    TextEntry::make('approval_status')
                        ->label(__('identity.columns.approval_status'))
                        ->badge()
                        ->color(function (mixed $state): string {
                            $value = $state instanceof State
                                ? $state::getMorphClass()
                                : ($state instanceof BackedEnum ? $state->value : (string) $state);

                            return match ($value) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected',
                                'suspended' => 'danger',
                                'changes_requested' => 'info',
                                default => 'gray',
                            };
                        })
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
                    TextEntry::make('business_name')
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
                ]),
            InfolistSection::make(__('identity.sections.approved_product_types'))
                ->schema([
                    TextEntry::make('approved_product_types')
                        ->label(__('identity.columns.active_type_approvals'))
                        ->getStateUsing(function (VendorProfile $record): string {
                            $types = $record->approvedTypes()
                                ->whereNull('revoked_at')
                                ->pluck('product_type')
                                ->map(fn ($t) => $t instanceof ProductType ? $t->value : $t)
                                ->map(fn (string $t): string => __('identity.product_type.'.$t))
                                ->all();

                            return $types ? implode('، ', $types) : __('identity.placeholders.none');
                        }),
                ]),
            AuditTimelineSection::make()
                ->audience(TimelineAudience::Admin)
                ->columnSpanFull(),
        ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            DocumentsRelationManager::class,
            ServicesRelationManager::class,
            BookingVendorsRelationManager::class,
            WalletRelationManager::class,
            WithdrawalsRelationManager::class,
            VendorReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorProfiles::route('/'),
            'edit' => EditVendorProfile::route('/{record}/edit'),
            'view' => ViewVendorProfile::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

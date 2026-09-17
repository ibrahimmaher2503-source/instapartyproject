<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Application\Actions\CreateAdminInterventionNoteAction;
use App\Modules\Booking\Application\Actions\EscalateLateVendorResponseAction;
use App\Modules\Booking\Application\Actions\ExtendVendorResponseDeadlineAction;
use App\Modules\Booking\Application\Actions\FreezeBookingChatAction;
use App\Modules\Booking\Application\Actions\ResumeBookingChatAction;
use App\Modules\Booking\Application\Actions\ResumeBookingReviewAction;
use App\Modules\Booking\Application\Actions\SendVendorReminderAction;
use App\Modules\Booking\Application\Actions\SuggestAlternativeVendorsAction;
use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Application\DTOs\SuggestedAlternativeVendorsDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\BookingLifecycleState;
use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource\Pages\ListBookingInterventions;
use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource\Pages\ViewBookingIntervention;
use App\Modules\Discovery\Domain\Contracts\AlternativeVendorFinder;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Filament\Infolists\Components\AuditTimelineSection;
use DomainException;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class AdminBookingInterventionResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $slug = 'admin-booking-intervention';

    protected static ?int $navigationSort = 60;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('booking.intervention.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('booking.intervention.nav_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.intervention.nav_label');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('booking.intervene.access') === true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('bookings.*')
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNotIn('lifecycle_status', ['completed', 'cancelled'])
            ->where(function (Builder $query): void {
                // Trouble bucket 1: late vendor response
                $query->orWhereExists(function ($sub): void {
                    $sub->from('booking_vendors')
                        ->whereColumn('booking_vendors.booking_id', 'bookings.id')
                        ->where('booking_vendors.sub_status', 'pending')
                        ->whereNotNull('booking_vendors.response_deadline')
                        ->where('booking_vendors.response_deadline', '<', now());
                });

                // Trouble bucket 2: all vendors rejected
                $query->orWhereExists(function ($sub): void {
                    $sub->from('booking_vendors as bv_check')
                        ->whereColumn('bv_check.booking_id', 'bookings.id')
                        ->whereNotExists(function ($inner) {
                            $inner->from('booking_vendors as bv_inner')
                                ->whereColumn('bv_inner.booking_id', 'bv_check.booking_id')
                                ->where('bv_inner.sub_status', '!=', 'rejected');
                        });
                });

                // Trouble bucket 3: customer review pending (open modification > 24h)
                $query->orWhereExists(function ($sub): void {
                    $sub->from('booking_modifications')
                        ->join('booking_vendors as bvm', 'bvm.id', '=', 'booking_modifications.booking_vendor_id')
                        ->whereColumn('bvm.booking_id', 'bookings.id')
                        ->where('booking_modifications.status', 'pending')
                        ->where('booking_modifications.created_at', '<', now()->subHours(24));
                });

                // Trouble bucket 4: stalled booking
                $stalledHours = config('booking.intervention.stalled_threshold_hours', 48);
                $query->orWhere(function (Builder $inner) use ($stalledHours): void {
                    $inner->whereIn('lifecycle_status', ['submitted', 'vendor_review'])
                        ->where('submitted_at', '<', now()->subHours($stalledHours));
                });
            });
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([]);
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            AuditTimelineSection::make()
                ->audience(TimelineAudience::Admin)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_no')
                    ->label(__('booking.intervention.columns.reference'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('customer.name')
                    ->label(__('booking.intervention.columns.customer'))
                    ->searchable()
                    ->sortable()
                    ->default(fn (Booking $record): string => '#'.$record->customer_id),

                TextColumn::make('lifecycle_status')
                    ->label(__('booking.intervention.columns.lifecycle_status'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof BookingLifecycleState
                        ? __('booking.lifecycle_status.'.$state->getValue())
                        : (string) $state)
                    ->color(fn ($state): string => match (is_string($state) ? $state : ($state?->value ?? '')) {
                        'submitted' => 'info',
                        'vendor_review' => 'warning',
                        'customer_review' => 'primary',
                        'confirmed' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('total_minor')
                    ->label(__('booking.intervention.columns.total'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),

                TextColumn::make('submitted_at')
                    ->label(__('booking.intervention.columns.nearest_deadline'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('event_starts_at')
                    ->label(__('booking.columns.event_starts_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('lifecycle_status')
                    ->label(__('booking.intervention.columns.lifecycle_status'))
                    ->options([
                        'submitted' => __('booking.lifecycle_status.submitted'),
                        'vendor_review' => __('booking.lifecycle_status.vendor_review'),
                        'customer_review' => __('booking.lifecycle_status.customer_review'),
                    ]),

                SelectFilter::make('product_type')
                    ->label(__('booking.intervention.columns.product_type'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }

                        return $query->whereExists(function ($sub) use ($data): void {
                            $sub->from('booking_items')
                                ->join('booking_vendors as bvp', 'bvp.id', '=', 'booking_items.booking_vendor_id')
                                ->whereColumn('bvp.booking_id', 'bookings.id')
                                ->where('booking_items.product_type', $data['value']);
                        });
                    })
                    ->options([
                        'rental' => __('booking.product_type.rental'),
                        'sale' => __('booking.product_type.sale'),
                        'digital' => __('booking.product_type.digital'),
                    ]),
            ])
            ->recordUrl(fn (Booking $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make(),

                Action::make('sendVendorReminder')
                    ->label(__('booking::booking.intervention.actions.send_vendor_reminder'))
                    ->icon('heroicon-o-bell')
                    ->color('warning')
                    ->visible(fn (Booking $record): bool => auth()->user()?->can('booking.intervene.send_vendor_reminder') === true &&
                        $record->vendors()->where('sub_status', 'pending')->exists()
                    )
                    ->form([
                        Textarea::make('note')
                            ->label(__('booking.intervention.fields.note_optional'))
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $bookingVendor = $record->vendors()->where('sub_status', 'pending')->first();
                        if (! $bookingVendor) {
                            Notification::make()
                                ->title(__('booking::booking.intervention.error.vendor_not_pending'))
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            app(SendVendorReminderAction::class)
                                ->execute($bookingVendor, auth()->id(), $data['note'] ?? null);

                            Notification::make()
                                ->title(__('booking::booking.intervention.success.send_vendor_reminder'))
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.send_vendor_reminder')),

                Action::make('escalateLateVendorResponse')
                    ->label(__('booking::booking.intervention.actions.escalate_vendor_timeout'))
                    ->icon('heroicon-o-clock')
                    ->color('danger')
                    ->visible(fn (Booking $record): bool => auth()->user()?->can('booking.intervene.escalate_vendor_timeout') === true &&
                        $record->vendors()
                            ->where('sub_status', 'pending')
                            ->where('response_deadline', '<', now())
                            ->exists()
                    )
                    ->form([
                        Textarea::make('reason')
                            ->label(__('booking.intervention.fields.escalation_reason'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $bookingVendor = $record->vendors()
                            ->where('sub_status', 'pending')
                            ->where('response_deadline', '<', now())
                            ->first();

                        if (! $bookingVendor) {
                            Notification::make()
                                ->title(__('booking.intervention.error.no_vendor_past_deadline'))
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            app(EscalateLateVendorResponseAction::class)
                                ->execute($bookingVendor, new AdminInterventionDTO(
                                    bookingId: $record->id,
                                    adminId: auth()->id(),
                                    interventionType: InterventionType::VendorTimeout,
                                    reason: $data['reason'],
                                    bookingVendorId: $bookingVendor->id,
                                ));

                            Notification::make()
                                ->title(__('booking::booking.intervention.success.escalate_vendor_timeout'))
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.escalate_vendor_timeout')),

                Action::make('extendVendorDeadline')
                    ->label(__('booking::booking.intervention.actions.extend_vendor_deadline'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Booking $record): bool => auth()->user()?->can('booking.intervene.extend_vendor_deadline') === true &&
                        $record->vendors()
                            ->whereIn('sub_status', ['pending', 'timed_out'])
                            ->exists()
                    )
                    ->form([
                        TextInput::make('hours')
                            ->label(__('booking::booking.intervention.fields.extend_hours'))
                            ->integer()
                            ->default(24)
                            ->minValue(1)
                            ->maxValue(72)
                            ->required(),
                        Textarea::make('reason')
                            ->label(__('booking::booking.intervention.fields.escalation_reason'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $bookingVendor = $record->vendors()
                            ->whereIn('sub_status', ['pending', 'timed_out'])
                            ->first();

                        if (! $bookingVendor) {
                            Notification::make()
                                ->title(__('booking::booking.intervention.error.no_vendor_past_deadline'))
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            app(ExtendVendorResponseDeadlineAction::class)->execute(
                                $bookingVendor,
                                new AdminInterventionDTO(
                                    bookingId: $record->id,
                                    adminId: auth()->id(),
                                    interventionType: InterventionType::DeadlineExtended,
                                    reason: $data['reason'],
                                    bookingVendorId: $bookingVendor->id,
                                ),
                                (int) $data['hours'],
                            );

                            Notification::make()
                                ->title(__('booking::booking.intervention.success.extend_vendor_deadline'))
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.extend_vendor_deadline')),

                Action::make('suggestAlternativeVendors')
                    ->label(__('booking::booking.intervention.actions.suggest_alternative_vendors'))
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->visible(fn (Booking $record): bool => auth()->user()?->can('booking.intervene.suggest_alternative_vendors') === true
                    )
                    ->form([
                        Hidden::make('idempotency_key')
                            ->default(fn (): string => (string) Str::uuid()),
                        Select::make('vendor_profile_ids')
                            ->label(__('booking.intervention.fields.suggest_vendors'))
                            ->multiple()
                            ->helperText(__('booking.intervention.fields.suggest_vendors_help'))
                            ->options(function (Booking $record): array {
                                try {
                                    $max = config('booking.intervention.suggest_max_candidates', 5);

                                    return app(AlternativeVendorFinder::class)
                                        ->findCandidates($record, $max)
                                        ->pluck('business_name', 'id')
                                        ->map(fn ($name) => is_array($name) ? ($name[app()->getLocale()] ?? $name['en'] ?? '') : $name)
                                        ->toArray();
                                } catch (Throwable) {
                                    return [];
                                }
                            })
                            ->required()
                            ->maxItems(config('booking.intervention.suggest_max_candidates', 5)),
                        Placeholder::make('suggestion_review')
                            ->label(__('booking.intervention.fields.review_summary'))
                            ->content(fn (Get $get): string => trans_choice(
                                'booking.intervention.fields.review_summary_body',
                                count((array) $get('vendor_profile_ids')),
                            )),
                        Textarea::make('reason')
                            ->label(__('booking.intervention.fields.reason'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        try {
                            app(SuggestAlternativeVendorsAction::class)
                                ->execute($record, new SuggestedAlternativeVendorsDTO(
                                    bookingId: $record->id,
                                    adminId: auth()->id(),
                                    vendorProfileIds: $data['vendor_profile_ids'],
                                    reason: $data['reason'],
                                    idempotencyKey: $data['idempotency_key'] ?? null,
                                ));

                            Notification::make()
                                ->title(__('booking::booking.intervention.success.suggest_alternative_vendors'))
                                ->success()->send();
                        } catch (DomainException|InvalidArgumentException $e) {
                            Notification::make()
                                ->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.suggest_alternative_vendors')),

                Action::make('resumeCustomerReview')
                    ->label(__('booking::booking.intervention.actions.resume_customer_review'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->visible(fn (Booking $record): bool => auth()->user()?->can('booking.intervene.resume_customer_review') === true &&
                        DB::table('booking_modifications')
                            ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_modifications.booking_vendor_id')
                            ->where('booking_vendors.booking_id', $record->id)
                            ->where('booking_modifications.status', 'pending')
                            ->where('booking_modifications.created_at', '<', now()->subHours(24))
                            ->exists()
                    )
                    ->form([
                        Textarea::make('note')
                            ->label(__('booking.intervention.fields.note_optional'))
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        try {
                            app(ResumeBookingReviewAction::class)
                                ->execute($record, auth()->id(), $data['note'] ?? null);

                            Notification::make()
                                ->title(__('booking::booking.intervention.success.resume_customer_review'))
                                ->success()->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.resume_customer_review')),

                Action::make('createInterventionNote')
                    ->label(__('booking::booking.intervention.actions.create_note'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (): bool => auth()->user()?->can('booking.intervene.create_note') === true)
                    ->form([
                        Textarea::make('note')
                            ->label(__('booking.intervention.fields.note'))
                            ->required()
                            ->minLength(1)
                            ->maxLength(2000)
                            ->rows(4),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        try {
                            app(CreateAdminInterventionNoteAction::class)
                                ->execute($record, auth()->id(), $data['note']);

                            Notification::make()
                                ->title(__('booking::booking.intervention.success.create_note'))
                                ->success()->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.create_note')),

                Action::make('freezeBookingChat')
                    ->label(__('booking::booking.intervention.actions.freeze_chat'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (Booking $record): bool => auth()->user()?->can('booking.intervene.freeze_chat') === true &&
                        DB::table('chat_threads')
                            ->where('booking_id', $record->id)
                            ->whereNull('frozen_at')
                            ->exists()
                    )
                    ->form([
                        Textarea::make('reason')
                            ->label(__('booking.intervention.fields.freeze_reason'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        try {
                            app(FreezeBookingChatAction::class)
                                ->execute($record->id, auth()->id(), $data['reason']);

                            Notification::make()
                                ->title(__('booking::booking.intervention.success.freeze_chat'))
                                ->success()->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.freeze_chat')),

                Action::make('resumeBookingChat')
                    ->label(__('booking::booking.intervention.actions.resume_chat'))
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => auth()->user()?->can('booking.intervene.freeze_chat') === true &&
                        DB::table('chat_threads')
                            ->where('booking_id', $record->id)
                            ->whereNotNull('frozen_at')
                            ->exists()
                    )
                    ->form([
                        Textarea::make('reason')
                            ->label(__('booking.intervention.fields.resume_reason'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        try {
                            app(ResumeBookingChatAction::class)
                                ->execute($record->id, auth()->id(), $data['reason']);

                            Notification::make()
                                ->title(__('booking::booking.intervention.success.resume_chat'))
                                ->success()->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.resume_chat')),
            ])
            ->defaultSort('submitted_at', 'asc')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookingInterventions::route('/'),
            'view' => ViewBookingIntervention::route('/{record}'),
        ];
    }
}

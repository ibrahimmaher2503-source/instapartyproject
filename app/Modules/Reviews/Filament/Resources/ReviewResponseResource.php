<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Filament\Resources;

use App\Modules\Reviews\Application\Actions\ModerateReviewResponseAction;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use App\Modules\Reviews\Filament\Resources\ReviewResponseResource\Pages\ListReviewResponses;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class ReviewResponseResource extends Resource
{
    protected static ?string $model = ReviewResponse::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 35;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.moderation');
    }

    public static function getNavigationLabel(): string
    {
        return self::getPluralModelLabel();
    }

    public static function getModelLabel(): string
    {
        return __('reviews.models.review_response.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reviews.models.review_response.plural');
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
        $query = parent::getEloquentQuery()->with('vendorProfile');
        $user = auth()->user();
        $types = [];

        if ($user?->can('view_any_service::review')) {
            $types[] = ReviewType::Service->value;
        }

        if ($user?->can('view_any_vendor::review')) {
            $types[] = ReviewType::Vendor->value;
        }

        if ($types === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereIn('review_type', $types)
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query
                            ->where('review_type', ReviewType::Service->value)
                            ->whereExists(function (QueryBuilder $query): void {
                                $query
                                    ->selectRaw('1')
                                    ->from('service_reviews')
                                    ->join('services', 'services.id', '=', 'service_reviews.service_id')
                                    ->whereColumn('service_reviews.id', 'review_responses.review_id')
                                    ->whereColumn('services.vendor_profile_id', 'review_responses.vendor_profile_id')
                                    ->whereNull('service_reviews.deleted_at');
                            });
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('review_type', ReviewType::Vendor->value)
                            ->whereExists(function (QueryBuilder $query): void {
                                $query
                                    ->selectRaw('1')
                                    ->from('vendor_reviews')
                                    ->whereColumn('vendor_reviews.id', 'review_responses.review_id')
                                    ->whereColumn('vendor_reviews.vendor_profile_id', 'review_responses.vendor_profile_id')
                                    ->whereNull('vendor_reviews.deleted_at');
                            });
                    });
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('review_type')
                    ->label(__('reviews.columns.review_type'))
                    ->badge()
                    ->formatStateUsing(fn (ReviewType $state): string => __('reviews.review_type.'.$state->value)),
                TextColumn::make('review_id')
                    ->label(__('reviews.columns.review_id'))
                    ->sortable(),
                TextColumn::make('vendorProfile.business_name')
                    ->label(__('reviews.columns.vendor'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—'))
                    ->searchable(),
                TextColumn::make('body')
                    ->label(__('reviews.response.body'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('locale')
                    ->label(__('reviews.columns.locale'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->value ?? '—'),
                TextColumn::make('moderation_status')
                    ->label(__('reviews.response.status'))
                    ->badge()
                    ->formatStateUsing(fn (ModerationStatus $state): string => __('reviews.moderation_status.'.$state->value)),
                TextColumn::make('created_at')
                    ->label(__('reviews.labels.submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('review_type')
                    ->label(__('reviews.columns.review_type'))
                    ->options(__('reviews.review_type')),
                SelectFilter::make('moderation_status')
                    ->label(__('reviews.response.status'))
                    ->options(ModerationStatus::class)
                    ->default(ModerationStatus::Pending->value),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('approve')
                    ->label(__('reviews.actions.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (ReviewResponse $record): bool => auth()->user()?->can('moderate', $record) ?? false)
                    ->visible(fn (ReviewResponse $record): bool => $record->moderation_status->canTransitionTo(ModerationStatus::Approved))
                    ->action(function (ReviewResponse $record): void {
                        app(ModerateReviewResponseAction::class)->execute(
                            $record,
                            ModerationStatus::Approved,
                        );
                        Notification::make()->title(__('reviews.notifications.approved_title'))->success()->send();
                    }),
                Action::make('reject')
                    ->label(__('reviews.actions.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->authorize(fn (ReviewResponse $record): bool => auth()->user()?->can('moderate', $record) ?? false)
                    ->visible(fn (ReviewResponse $record): bool => $record->moderation_status->canTransitionTo(ModerationStatus::Rejected))
                    ->form([
                        Textarea::make('reason_en')
                            ->label(__('reviews.labels.reason_en'))
                            ->required()
                            ->maxLength(1000),
                        Textarea::make('reason_ar')
                            ->label(__('reviews.labels.reason_ar'))
                            ->maxLength(1000)
                            ->extraInputAttributes(['dir' => 'rtl']),
                    ])
                    ->action(function (ReviewResponse $record, array $data): void {
                        app(ModerateReviewResponseAction::class)->execute(
                            $record,
                            ModerationStatus::Rejected,
                            array_filter(['en' => $data['reason_en'], 'ar' => $data['reason_ar'] ?? null]),
                        );
                        Notification::make()->title(__('reviews.notifications.rejected_title'))->danger()->send();
                    }),
                Action::make('hide')
                    ->label(__('reviews.actions.hide'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->authorize(fn (ReviewResponse $record): bool => auth()->user()?->can('moderate', $record) ?? false)
                    ->visible(fn (ReviewResponse $record): bool => $record->moderation_status->canTransitionTo(ModerationStatus::Hidden))
                    ->action(function (ReviewResponse $record): void {
                        app(ModerateReviewResponseAction::class)->execute(
                            $record,
                            ModerationStatus::Hidden,
                        );
                        Notification::make()->title(__('reviews.notifications.hidden_title'))->info()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewResponses::route('/'),
        ];
    }
}

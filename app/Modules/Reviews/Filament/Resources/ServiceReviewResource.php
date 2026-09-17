<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Filament\Resources;

use App\Modules\Reviews\Application\Actions\ModerateReviewAction;
use App\Modules\Reviews\Application\DTOs\ModerateReviewData;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewLocale;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Filament\Resources\ServiceReviewResource\Pages\ListServiceReviews;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceReviewResource extends Resource
{
    protected static ?string $model = ServiceReview::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.moderation');
    }

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('reviews.nav.service_reviews');
    }

    public static function getModelLabel(): string
    {
        return __('reviews.models.service_review.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reviews.models.service_review.plural');
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
        return parent::getEloquentQuery()->with(['service', 'bookingItem', 'reviewer']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('reviews.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('service.name')
                    ->label(__('reviews.columns.service'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'service',
                        fn ($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) LIKE ?", ["%{$search}%"])
                    )),
                TextColumn::make('reviewer.name')
                    ->label(__('reviews.columns.reviewer'))
                    ->searchable(),
                TextColumn::make('rating')
                    ->label(__('reviews.columns.rating'))
                    ->sortable(),
                TextColumn::make('locale')
                    ->label(__('reviews.columns.locale'))
                    ->badge()
                    ->formatStateUsing(fn (ReviewLocale $state): string => $state->value),
                TextColumn::make('moderation_status')
                    ->label(__('reviews.columns.moderation_status'))
                    ->badge()
                    ->formatStateUsing(fn (ModerationStatus $state): string => __('reviews.moderation_status.'.$state->value)),
                TextColumn::make('bookingItem.public_id')
                    ->label(__('reviews.columns.booking_item'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('approve')
                    ->label(__('reviews.actions.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ServiceReview $record): bool => $record->moderation_status === ModerationStatus::Pending
                        || $record->moderation_status === ModerationStatus::Hidden)
                    ->action(function (ServiceReview $record): void {
                        app(ModerateReviewAction::class)->execute(
                            $record,
                            new ModerateReviewData(ModerationStatus::Approved, (int) auth()->id())
                        );
                        Notification::make()->title(__('reviews.notifications.approved_title'))->success()->send();
                    }),

                Action::make('reject')
                    ->label(__('reviews.actions.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ServiceReview $record): bool => $record->moderation_status === ModerationStatus::Pending
                        || $record->moderation_status === ModerationStatus::Approved)
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
                    ->action(function (ServiceReview $record, array $data): void {
                        app(ModerateReviewAction::class)->execute(
                            $record,
                            new ModerateReviewData(
                                ModerationStatus::Rejected,
                                (int) auth()->id(),
                                array_filter(['en' => $data['reason_en'], 'ar' => $data['reason_ar'] ?? null])
                            )
                        );
                        Notification::make()->title(__('reviews.notifications.rejected_title'))->danger()->send();
                    }),

                Action::make('hide')
                    ->label(__('reviews.actions.hide'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (ServiceReview $record): bool => $record->moderation_status === ModerationStatus::Approved)
                    ->action(function (ServiceReview $record): void {
                        app(ModerateReviewAction::class)->execute(
                            $record,
                            new ModerateReviewData(ModerationStatus::Hidden, (int) auth()->id())
                        );
                        Notification::make()->title(__('reviews.notifications.hidden_title'))->info()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceReviews::route('/'),
        ];
    }
}

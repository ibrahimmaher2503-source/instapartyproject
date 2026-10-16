<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources;

use App\Modules\Shared\Application\Services\AdminQueueSlaSettings;
use App\Modules\Shared\Domain\Enums\AdminQueue;
use App\Modules\Shared\Filament\Resources\RelationManagers\InternalRecordNotesRelationManager;
use App\Modules\Shared\Filament\Tables\OperationalDateFilter;
use App\Modules\Shared\Filament\Tables\OperationalExport;
use App\Modules\Shared\Filament\Tables\ReferenceSearch;
use App\Modules\Shared\Filament\Tables\ScopedBulkAction;
use App\Modules\TrustSafety\Application\Actions\DismissReportAction;
use App\Modules\TrustSafety\Application\Actions\MarkReportUnderReviewAction;
use App\Modules\TrustSafety\Application\Actions\ResolveReportAction;
use App\Modules\TrustSafety\Domain\Enums\ReportReason;
use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
use App\Modules\TrustSafety\Domain\Models\Report;
use App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages\ListReports;
use App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages\ViewReport;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.trust_safety');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([OperationalExport::make(static::class, ['public_id', 'status', 'reason', 'created_at'])])
            ->columns([
                TextColumn::make('reporter.name')
                    ->label('Reporter')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $match) use ($search): void {
                        ReferenceSearch::apply($match, $search, [
                            '' => ['public_id'],
                            'reporter' => ['public_id', 'name', 'email', 'phone_e164'],
                        ]);
                        $vendorType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
                        $match->orWhere(fn (Builder $target): Builder => $target
                            ->whereIn('reportable_type', ['vendor', 'vendor_profile', $vendorType])
                            ->whereIn('reportable_id', ReferenceSearch::apply($vendorType::query(), $search, [
                                '' => ['public_id', 'business_name->en', 'business_name->ar'],
                                'user' => ['public_id', 'name', 'email', 'phone_e164'],
                            ])->select('id')));
                    })),

                TextColumn::make('reportable_type')
                    ->badge()
                    ->label(__('trust-safety::trust-safety.fields.target_type'))
                    ->formatStateUsing(fn (?string $state): string => self::formatReportableType($state)),

                TextColumn::make('reason')
                    ->badge()
                    ->color(fn (ReportReason $state): string => match ($state) {
                        ReportReason::FraudulentActivity => 'danger',
                        ReportReason::Harassment => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (ReportReason $state) => $state->label())
                    ->label(__('trust-safety::trust-safety.fields.reason')),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ReportStatus $state): string => match ($state) {
                        ReportStatus::Open => 'warning',
                        ReportStatus::UnderReview => 'info',
                        ReportStatus::Resolved => 'success',
                        ReportStatus::Dismissed => 'gray',
                    })
                    ->formatStateUsing(fn (ReportStatus $state) => $state->label())
                    ->label(__('trust-safety::trust-safety.fields.status')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('trust-safety::trust-safety.fields.submitted')),
                TextColumn::make('queue_age')
                    ->label(__('shared::shared.admin_queue_sla.queue_age'))
                    ->getStateUsing(fn (Report $record): ?string => self::isInReviewQueue($record)
                        ? Carbon::parse($record->created_at, 'UTC')->diffForHumans(now('UTC'))
                        : null)
                    ->placeholder(__('shared::shared.admin_queue_sla.not_active')),
                TextColumn::make('sla_deadline')
                    ->label(__('shared::shared.admin_queue_sla.deadline'))
                    ->dateTime('d M Y H:i')
                    ->timezone(fn () => auth()->user()?->timezone ?? 'Africa/Cairo')
                    ->getStateUsing(fn (Report $record) => self::isInReviewQueue($record)
                        ? app(AdminQueueSlaSettings::class)->deadlineFor(AdminQueue::Report, $record->created_at)
                        : null)
                    ->suffix(fn (Report $record): ?string => self::isInReviewQueue($record)
                        ? ' '.(auth()->user()?->timezone ?? 'Africa/Cairo')
                        : null)
                    ->placeholder(__('shared::shared.admin_queue_sla.not_active')),
                TextColumn::make('sla_status')
                    ->label(__('shared::shared.admin_queue_sla.status'))
                    ->badge()
                    ->getStateUsing(fn (Report $record): string => self::slaStatus($record))
                    ->color(fn (Report $record): string => self::isInReviewQueue($record)
                        ? (app(AdminQueueSlaSettings::class)->isOverdue(AdminQueue::Report, $record->created_at) ? 'danger' : 'success')
                        : 'gray'),
            ])
            ->filters([
                OperationalDateFilter::make('created_at'),
                Filter::make('overdue')
                    ->label(__('shared::shared.admin_queue_sla.overdue'))
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', [ReportStatus::Open->value, ReportStatus::UnderReview->value])
                        ->where('created_at', '<=', now('UTC')->subHours(app(AdminQueueSlaSettings::class)->hours(AdminQueue::Report)))),
                SelectFilter::make('status')
                    ->options(collect(ReportStatus::cases())->mapWithKeys(
                        fn (ReportStatus $s) => [$s->value => $s->label()]
                    ))
                    ->label(__('trust-safety::trust-safety.fields.status')),

                SelectFilter::make('reason')
                    ->options(collect(ReportReason::cases())->mapWithKeys(
                        fn (ReportReason $r) => [$r->value => $r->label()]
                    ))
                    ->label(__('trust-safety::trust-safety.fields.reason')),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('markUnderReview')
                    ->label('Mark Under Review')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Report $record): bool => $record->status === ReportStatus::Open)
                    ->action(function (Report $record): void {
                        app(MarkReportUnderReviewAction::class)->execute($record);
                        Notification::make()->title('Report marked under review')->info()->send();
                    }),

                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Report $record): bool => $record->status === ReportStatus::UnderReview)
                    ->action(function (Report $record): void {
                        app(ResolveReportAction::class)->execute($record);
                        Notification::make()->title('Report resolved')->success()->send();
                    }),

                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Report $record): bool => $record->status === ReportStatus::UnderReview)
                    ->action(function (Report $record): void {
                        app(DismissReportAction::class)->execute($record);
                        Notification::make()->title('Report dismissed')->warning()->send();
                    }),
            ])
            ->bulkActions([
                ScopedBulkAction::make(
                    'markSelectedUnderReview',
                    'Mark Under Review',
                    'update_report',
                    static::class,
                    fn (Report $record) => app(MarkReportUnderReviewAction::class)->execute($record),
                ),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [
                    ReportStatus::Open->value,
                    ReportStatus::UnderReview->value,
                ])
                ->orderBy('created_at', 'asc'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
            'view' => ViewReport::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [InternalRecordNotesRelationManager::class];
    }

    private static function isInReviewQueue(Report $record): bool
    {
        return in_array($record->status, [ReportStatus::Open, ReportStatus::UnderReview], true);
    }

    private static function slaStatus(Report $record): string
    {
        if (! self::isInReviewQueue($record)) {
            return __('shared::shared.admin_queue_sla.not_active');
        }

        return app(AdminQueueSlaSettings::class)->isOverdue(AdminQueue::Report, $record->created_at)
            ? __('shared::shared.admin_queue_sla.overdue')
            : __('shared::shared.admin_queue_sla.within_sla');
    }

    public static function formatReportableType(?string $state): string
    {
        if ($state === null || $state === '') {
            return '';
        }

        $normalized = class_basename($state);
        $snake = str($normalized)->snake()->toString();

        $key = 'trust-safety::trust-safety.target_types.'.$snake;
        if (Lang::has($key)) {
            return __($key);
        }

        return str($snake)->replace('_', ' ')->title()->toString();
    }
}

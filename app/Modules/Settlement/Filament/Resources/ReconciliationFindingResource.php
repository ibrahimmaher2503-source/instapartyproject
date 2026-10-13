<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Application\Actions\AssignReconciliationFindingAction;
use App\Modules\Settlement\Application\Actions\IgnoreReconciliationFindingAction;
use App\Modules\Settlement\Application\Support\FinanceIdentityPresenter;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Filament\Resources\ReconciliationFindingResource\Pages\ListReconciliationFindings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReconciliationFindingResource extends Resource
{
    protected static ?string $model = ReconciliationFinding::class;

    protected static ?string $recordRouteKeyName = 'public_id';

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?int $navigationSort = 100;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.reconciliation_findings');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.reconciliation_finding.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.reconciliation_finding.plural');
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('settlement.columns.public_id'))
                    ->searchable()
                    ->copyable(),

                TextColumn::make('run.public_id')
                    ->label(__('settlement.columns.run_id'))
                    ->searchable()
                    ->url(fn (ReconciliationFinding $record): string => ReconciliationRunResource::getUrl('view', ['record' => $record->run?->public_id])
                    ),

                TextColumn::make('finding_type')
                    ->badge()
                    ->color(fn (ReconciliationFindingType $state): string => match ($state) {
                        ReconciliationFindingType::WalletCacheDrift => 'warning',
                        ReconciliationFindingType::OrphanedRefundRow => 'danger',
                        ReconciliationFindingType::OrphanedLedgerEntry => 'danger',
                        ReconciliationFindingType::UnbalancedTransactionGroup => 'danger',
                        ReconciliationFindingType::CommissionWithoutSnapshotRate => 'danger',
                        ReconciliationFindingType::WithdrawalWithoutReserveEntry => 'danger',
                        ReconciliationFindingType::NegativeVendorBalance => 'danger',
                        ReconciliationFindingType::CurrencyMismatch => 'danger',
                    })
                    ->label(__('settlement.columns.finding_type')),

                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (ReconciliationFindingSeverity $state): string => match ($state) {
                        ReconciliationFindingSeverity::Info => 'info',
                        ReconciliationFindingSeverity::Warning => 'warning',
                        ReconciliationFindingSeverity::High => 'danger',
                    })
                    ->label(__('settlement.columns.severity')),

                TextColumn::make('resource_type')
                    ->label(__('settlement.columns.resource'))
                    ->state(fn (ReconciliationFinding $record): string => FinanceIdentityPresenter::reference(
                        $record->resource_type,
                        $record->resource_id,
                    )),

                TextColumn::make('resolution')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'auto_repaired' => 'success',
                        'ignored_known_issue' => 'gray',
                        default => 'warning',
                    })
                    ->default(__('settlement.findings.unresolved'))
                    ->label(__('settlement.columns.resolution')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('settlement.columns.detected_at')),

                TextColumn::make('last_seen_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('settlement.columns.last_seen_at')),

                TextColumn::make('resolution_note')
                    ->wrap()
                    ->label(__('settlement.actions.ignore_reason')),

                TextColumn::make('assignedTo.name')
                    ->label(__('settlement.columns.assigned_to')),
            ])
            ->filters([
                Filter::make('current_conditions')
                    ->label(__('settlement.findings.current_conditions'))
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->canonicalConditions()),
                SelectFilter::make('severity')
                    ->options(ReconciliationFindingSeverity::class)
                    ->label(__('settlement.columns.severity')),

                SelectFilter::make('finding_type')
                    ->options(ReconciliationFindingType::class)
                    ->label(__('settlement.columns.finding_type')),

                SelectFilter::make('resolution')
                    ->options([
                        'auto_repaired' => __('settlement.findings.auto_repaired'),
                        'ignored_known_issue' => __('settlement.findings.ignored'),
                    ])
                    ->label(__('settlement.columns.resolution')),
            ])
            ->actions([
                Action::make('assign')
                    ->label(__('settlement.actions.assign_finding'))
                    ->form([
                        Select::make('admin_id')
                            ->label(__('settlement.columns.assigned_to'))
                            ->options(fn () => DB::table('users')
                                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                                ->where('users.status', 'active')
                                ->whereNull('users.deleted_at')
                                ->where('model_has_roles.model_type', 'App\\Modules\\Identity\\Domain\\Models\\User')
                                ->where('roles.guard_name', 'web')
                                ->whereIn('roles.name', ['admin', 'super_admin'])
                                ->distinct()->pluck('users.name', 'users.id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(fn (ReconciliationFinding $record, array $data) => app(AssignReconciliationFindingAction::class)->execute($record, (int) $data['admin_id']))
                    ->visible(fn (ReconciliationFinding $record): bool => $record->resolved_at === null
                        && (auth()->user()?->can('update', $record) ?? false)),
                Action::make('markIgnored')
                    ->label(__('settlement.actions.mark_ignored'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(__('settlement.actions.mark_ignored_heading'))
                    ->modalDescription(__('settlement.actions.mark_ignored_description'))
                    ->form([
                        Textarea::make('reason')
                            ->label(__('settlement.actions.ignore_reason'))
                            ->required()
                            ->maxLength(2000)
                            ->rows(3),
                    ])
                    ->action(function (ReconciliationFinding $record, array $data): void {
                        app(IgnoreReconciliationFindingAction::class)->execute($record, (string) $data['reason']);

                        Notification::make()
                            ->title(__('settlement.actions.marked_ignored_success'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (ReconciliationFinding $record): bool => $record->resolution === null
                        && (auth()->user()?->can('update', $record) ?? false)),
            ]);
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReconciliationFindings::route('/'),
        ];
    }
}

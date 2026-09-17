<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Pages;

use App\Modules\Payments\Application\Actions\ManualCapturePaymentAction;
use App\Modules\Payments\Application\Actions\MarkPaymentAbandonedAction;
use App\Modules\Payments\Application\Actions\OpenChargebackAction;
use App\Modules\Payments\Application\Actions\ReplayWebhookAction;
use App\Modules\Payments\Application\Actions\ResolveChargebackAction;
use App\Modules\Payments\Application\Actions\RetryFailedPaymentAction;
use App\Modules\Payments\Application\Actions\VoidStuckAuthorizationAction;
use App\Modules\Payments\Application\DTOs\OpenChargebackDto;
use App\Modules\Payments\Application\DTOs\ResolveChargebackDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Enums\ChargebackStatus;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\GatewayHealthPing;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentChargeback;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class PaymentsOpsConsole extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    protected static string $view = 'payments::filament.pages.payments-ops-console';

    public string $activeTab = 'failed_payments';

    public static function getNavigationLabel(): string
    {
        return __('payments.ops.navigation_label');
    }

    public function getTitle(): string
    {
        return __('payments.ops.page_title');
    }

    public function tabs(): array
    {
        return [
            'failed_payments' => __('payments.ops.tabs.failed_payments'),
            'stuck_auths' => __('payments.ops.tabs.stuck_auths'),
            'webhook_replay' => __('payments.ops.tabs.webhook_replay'),
            'chargebacks' => __('payments.ops.tabs.chargebacks'),
            'gateway_health' => __('payments.ops.tabs.gateway_health'),
            'reconciliation' => __('payments.ops.tabs.reconciliation'),
        ];
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return match ($this->activeTab) {
            'failed_payments' => $this->failedPaymentsTable($table),
            'stuck_auths' => $this->stuckAuthsTable($table),
            'webhook_replay' => $this->webhookReplayTable($table),
            'chargebacks' => $this->chargebacksTable($table),
            'gateway_health' => $this->gatewayHealthTable($table),
            'reconciliation' => $this->reconciliationTable($table),
            default => $this->failedPaymentsTable($table),
        };
    }

    private function failedPaymentsTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Payment::query()
                ->where('status', PaymentStatus::Failed)
                ->latest()
            )
            ->columns([
                TextColumn::make('public_id')->label(__('payments.ops.columns.id'))->searchable()->limit(12),
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('gateway_ref')->label(__('payments.ops.columns.gateway_ref'))->limit(20),
                TextColumn::make('amount_minor')->label(__('payments.ops.columns.amount'))->money('EGP', divideBy: 100)->sortable(),
                TextColumn::make('failure_code')->badge()->color('danger'),
                TextColumn::make('created_at')->label(__('payments.ops.columns.failed_at'))->dateTime()->sortable(),
            ])
            ->actions([
                TableAction::make('retry')
                    ->label(__('payments.ops.actions.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Payment $record): void {
                        app(RetryFailedPaymentAction::class)->execute($record->id, auth()->id());
                        Notification::make()->title(__('payments.ops.retry_queued'))->success()->send();
                    }),

                TableAction::make('abandon')
                    ->label(__('payments.ops.actions.abandon'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Payment $record): void {
                        app(MarkPaymentAbandonedAction::class)->execute($record->id, auth()->id());
                        Notification::make()->title(__('payments.ops.abandoned'))->warning()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function stuckAuthsTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Payment::query()
                ->where('status', PaymentStatus::Authorized)
                ->where('created_at', '<', now()->subHours(24))
                ->latest()
            )
            ->columns([
                TextColumn::make('public_id')->label(__('payments.ops.columns.id'))->searchable()->limit(12),
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('gateway_ref')->label(__('payments.ops.columns.gateway_ref'))->limit(20),
                TextColumn::make('amount_minor')->label(__('payments.ops.columns.amount'))->money('EGP', divideBy: 100)->sortable(),
                TextColumn::make('created_at')->label(__('payments.ops.columns.authorized_at'))->dateTime()->sortable(),
            ])
            ->actions([
                TableAction::make('capture')
                    ->label(__('payments.ops.actions.capture'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Textarea::make('reason')
                            ->label(__('payments.ops.forms.reason_manual_capture'))
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        app(ManualCapturePaymentAction::class)->execute($record->id, auth()->id(), $data['reason']);
                        Notification::make()->title(__('payments.ops.captured'))->success()->send();
                    }),

                TableAction::make('void')
                    ->label(__('payments.ops.actions.void'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')
                            ->label(__('payments.ops.forms.reason_void'))
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        app(VoidStuckAuthorizationAction::class)->execute($record->id, auth()->id(), $data['reason']);
                        Notification::make()->title(__('payments.ops.voided'))->warning()->send();
                    }),
            ])
            ->defaultSort('created_at', 'asc');
    }

    private function webhookReplayTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => GatewayWebhookLog::query()
                ->where('signature_valid', true)
                ->latest()
            )
            ->columns([
                TextColumn::make('id')->label(__('payments.ops.columns.id'))->sortable(),
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('event_type')->badge()->color('info'),
                TextColumn::make('processed_at')->label(__('payments.ops.columns.processed_at'))->dateTime()->placeholder(__('payments.ops.placeholders.not_processed')),
                TextColumn::make('processing_error')->label(__('payments.ops.columns.error'))->limit(40)->placeholder(__('payments.ops.placeholders.none')),
                TextColumn::make('created_at')->label(__('payments.ops.columns.received_at'))->dateTime()->sortable(),
            ])
            ->actions([
                TableAction::make('replay')
                    ->label(__('payments.ops.actions.replay'))
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('payments.ops.modal.replay_description'))
                    ->action(function (GatewayWebhookLog $record): void {
                        app(ReplayWebhookAction::class)->execute($record->id, auth()->id());
                        Notification::make()->title(__('payments.ops.webhook_replayed'))->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function chargebacksTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PaymentChargeback::query()->with('payment')->latest('opened_at'))
            ->columns([
                TextColumn::make('public_id')->label(__('payments.ops.columns.id'))->searchable()->limit(12),
                TextColumn::make('payment.gateway_ref')->label(__('payments.ops.columns.gateway_ref'))->limit(20),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ChargebackStatus $state): string => $state->color()),
                TextColumn::make('amount_minor')->label(__('payments.ops.columns.amount'))->money('EGP', divideBy: 100)->sortable(),
                TextColumn::make('gateway_case_id')->label(__('payments.ops.columns.case_id'))->placeholder(__('payments.ops.placeholders.none')),
                TextColumn::make('opened_at')->label(__('payments.ops.columns.opened_at'))->dateTime()->sortable(),
                TextColumn::make('resolved_at')->label(__('payments.ops.columns.resolved_at'))->dateTime()->placeholder(__('payments.ops.placeholders.pending')),
            ])
            ->headerActions([
                TableAction::make('open_chargeback')
                    ->label(__('payments.ops.actions.open_chargeback'))
                    ->icon('heroicon-o-plus-circle')
                    ->color('danger')
                    ->form([
                        TextInput::make('payment_id')->label(__('payments.ops.forms.payment_id'))->integer()->required(),
                        Textarea::make('reason_en')->label(__('payments.ops.forms.reason_en'))->required(),
                        Textarea::make('reason_ar')->label(__('payments.ops.forms.reason_ar'))->required(),
                        TextInput::make('amount_minor')->label(__('payments.ops.forms.amount_minor'))->integer()->required(),
                        TextInput::make('gateway_case_id')->label(__('payments.ops.forms.gateway_case_id'))->nullable(),
                    ])
                    ->action(function (array $data): void {
                        app(OpenChargebackAction::class)->execute(new OpenChargebackDto(
                            paymentId: (int) $data['payment_id'],
                            adminUserId: auth()->id(),
                            reason: ['en' => $data['reason_en'], 'ar' => $data['reason_ar']],
                            amountMinor: (int) $data['amount_minor'],
                            amountCurrency: 'EGP',
                            gatewayCaseId: $data['gateway_case_id'] ?? null,
                        ));
                        Notification::make()->title(__('payments::chargebacks.opened_successfully'))->success()->send();
                    }),
            ])
            ->actions([
                TableAction::make('resolve')
                    ->label(__('payments.ops.actions.resolve'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->form([
                        Select::make('status')
                            ->label(__('payments.ops.forms.resolution'))
                            ->options([
                                ChargebackStatus::Won->value => __('payments.ops.forms.resolution_won'),
                                ChargebackStatus::Lost->value => __('payments.ops.forms.resolution_lost'),
                            ])
                            ->required(),
                        Textarea::make('admin_notes_en')->label(__('payments.ops.forms.admin_notes_en')),
                        Textarea::make('admin_notes_ar')->label(__('payments.ops.forms.admin_notes_ar')),
                    ])
                    ->action(function (PaymentChargeback $record, array $data): void {
                        app(ResolveChargebackAction::class)->execute(new ResolveChargebackDto(
                            chargebackId: $record->id,
                            adminUserId: auth()->id(),
                            status: ChargebackStatus::from($data['status']),
                            adminNotes: isset($data['admin_notes_en'])
                                ? ['en' => $data['admin_notes_en'], 'ar' => $data['admin_notes_ar'] ?? '']
                                : null,
                        ));
                        Notification::make()->title(__('payments::chargebacks.resolved_successfully', ['status' => $data['status']]))->success()->send();
                    })
                    ->visible(fn (PaymentChargeback $record): bool => ! $record->isResolved()),
            ])
            ->defaultSort('opened_at', 'desc');
    }

    private function gatewayHealthTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => GatewayHealthPing::query()
                ->where('checked_at', '>=', now()->subHours(24))
                ->latest('checked_at')
            )
            ->columns([
                TextColumn::make('gateway_code')->badge()->color('gray'),
                TextColumn::make('success')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('payments.ops.gateway_status.ok') : __('payments.ops.gateway_status.fail'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('latency_ms')->label(__('payments.ops.columns.latency_ms'))->sortable(),
                TextColumn::make('error_message')->label(__('payments.ops.columns.error'))->limit(60)->placeholder(__('payments.ops.placeholders.none')),
                TextColumn::make('checked_at')->label(__('payments.ops.columns.checked_at'))->dateTime()->sortable(),
            ])
            ->defaultSort('checked_at', 'desc');
    }

    private function reconciliationTable(Table $table): Table
    {
        $platformCount = Payment::query()
            ->where('status', PaymentStatus::Captured)
            ->whereDate('captured_at', today())
            ->count();

        try {
            $gatewayCount = app(PaymentGateway::class)->getTodayCapturedCount();
            $source = __('payments.ops.gateway_status.ok');
        } catch (Throwable) {
            $gatewayCount = null;
            $source = __('payments.ops.gateway_status.fail');
        }

        $diff = $gatewayCount !== null ? ($gatewayCount - $platformCount) : null;

        // Use a simple static dataset rendered as a table
        return $table
            ->query(fn (): Builder => Payment::query()
                ->where('status', PaymentStatus::Captured)
                ->whereDate('captured_at', today())
                ->latest('captured_at')
            )
            ->columns([
                TextColumn::make('public_id')->label(__('payments.ops.columns.id'))->limit(12),
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('gateway_ref')->label(__('payments.ops.columns.gateway_ref'))->limit(20),
                TextColumn::make('amount_minor')->label(__('payments.ops.columns.amount'))->money('EGP', divideBy: 100),
                TextColumn::make('captured_at')->label(__('payments.columns.captured_at'))->dateTime()->sortable(),
            ])
            ->heading(__('payments.ops.reconciliation_heading', [
                'platform' => $platformCount,
                'gateway' => $gatewayCount ?? __('payments.ops.reconciliation_na'),
                'diff' => $diff !== null
                    ? ($diff === 0 ? __('payments.ops.reconciliation_diff_none') : (string) $diff)
                    : __('payments.ops.reconciliation_na'),
                'source' => $source,
            ]))
            ->defaultSort('captured_at', 'desc');
    }
}

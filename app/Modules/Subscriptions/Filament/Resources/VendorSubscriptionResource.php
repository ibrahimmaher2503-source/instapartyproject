<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources;

use App\Modules\Subscriptions\Application\Actions\ApplyAdminTierOverrideAction;
use App\Modules\Subscriptions\Application\Actions\RevokeAdminTierOverrideAction;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use App\Modules\Subscriptions\Filament\Resources\VendorSubscriptionResource\Pages\ListVendorSubscriptions;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VendorSubscriptionResource extends Resource
{
    protected static ?string $model = VendorSubscription::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.subscriptions');
    }

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('subscriptions::subscription.vendor_subs');
    }

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function form(Form $schema): Form
    {
        return $schema
            ->components([
                TextInput::make('public_id')
                    ->disabled(),

                TextInput::make('vendor.business_name')
                    ->label(__('subscriptions::subscription.vendor'))
                    ->disabled(),

                TextInput::make('plan.plan_code')
                    ->label(__('subscriptions::subscription.plan_code'))
                    ->disabled(),

                TextInput::make('status')
                    ->disabled(),

                Toggle::make('is_admin_override')
                    ->label(__('subscriptions::subscription.admin_override'))
                    ->disabled(),

                DateTimePicker::make('started_at')
                    ->disabled(),

                DateTimePicker::make('current_period_end')
                    ->label(__('subscriptions::subscription.expires_at'))
                    ->disabled(),

                DateTimePicker::make('override_expires_at')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendor.business_name')
                    ->label(__('subscriptions::subscription.vendor'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('plan.plan_code')
                    ->label(__('subscriptions::subscription.plan_code'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'free' => 'gray',
                        'silver' => 'info',
                        'gold' => 'warning',
                        'premium' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state->value ?? $state) {
                        SubscriptionStatus::Active->value, SubscriptionStatus::Active => 'success',
                        SubscriptionStatus::PastDue->value, SubscriptionStatus::PastDue => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => $state instanceof SubscriptionStatus ? $state->label() : $state)
                    ->sortable(),

                IconColumn::make('is_admin_override')
                    ->label(__('subscriptions::subscription.is_admin_override'))
                    ->boolean(),

                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('current_period_end')
                    ->label(__('subscriptions::subscription.expires_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('override_expires_at')
                    ->label(__('subscriptions::subscription.override_expires_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SubscriptionStatus::class),

                TernaryFilter::make('is_admin_override')
                    ->label(__('subscriptions::subscription.admin_override_only')),

                SelectFilter::make('plan')
                    ->options(fn () => SubscriptionPlan::query()
                        ->orderBy('plan_code')
                        ->get()
                        ->mapWithKeys(function (SubscriptionPlan $p) {
                            $code = $p->plan_code;

                            return [$p->id => $code instanceof BackedEnum ? $code->value : (string) $code];
                        })
                        ->all()),
            ])
            ->actions([
                Action::make('overrideTier')
                    ->label(__('subscriptions::subscription.override_tier'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Select::make('plan_id')
                            ->label(__('subscriptions::subscription.plan_code'))
                            ->options(fn () => SubscriptionPlan::query()
                                ->where('is_published', true)
                                ->get()
                                ->mapWithKeys(function (SubscriptionPlan $p) {
                                    $label = $p->getTranslation('name', app()->getLocale(), false)
                                        ?: $p->getTranslation('name', 'en', false);
                                    if (! $label) {
                                        $code = $p->plan_code;
                                        $label = $code instanceof BackedEnum ? $code->value : (string) $code;
                                    }

                                    return [$p->id => $label];
                                })
                                ->all())
                            ->required(),

                        TextInput::make('reason_en')
                            ->label(__('subscriptions::subscription.override_reason_en'))
                            ->required()
                            ->maxLength(500),

                        TextInput::make('reason_ar')
                            ->label(__('subscriptions::subscription.override_reason_ar'))
                            ->required()
                            ->maxLength(500)
                            ->extraInputAttributes(['dir' => 'rtl']),

                        DateTimePicker::make('override_expires_at')
                            ->label(__('subscriptions::subscription.override_expires_at'))
                            ->native(false)
                            ->minDate(now()->addDay()),
                    ])
                    ->action(function (VendorSubscription $record, array $data): void {
                        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
                        $expiresAt = isset($data['override_expires_at']) ? Carbon::parse($data['override_expires_at']) : null;

                        app(ApplyAdminTierOverrideAction::class)->execute(
                            $record->vendor_profile_id,
                            $plan,
                            $data['reason_en'],
                            $data['reason_ar'],
                            $expiresAt,
                        );

                        Notification::make()
                            ->title(__('subscriptions::subscription.override_applied'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()->can('override_vendor_subscription')),

                Action::make('revokeOverride')
                    ->label(__('subscriptions::subscription.revoke_override'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (VendorSubscription $record): void {
                        app(RevokeAdminTierOverrideAction::class)->execute($record);

                        Notification::make()
                            ->title(__('subscriptions::subscription.override_revoked'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (VendorSubscription $record) => $record->is_admin_override && ! $record->status->isTerminal()),

                Action::make('sendRenewalReminder')
                    ->label(__('subscriptions::subscription.send_renewal_reminder'))
                    ->icon('heroicon-o-bell')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (VendorSubscription $record): void {
                        Notification::make()
                            ->title(__('subscriptions::subscription.renewal_reminder_sent'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (VendorSubscription $record): bool => $record->status === SubscriptionStatus::Active),

                ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorSubscriptions::route('/'),
        ];
    }

    public static function getHeaderActions(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource\Pages;

use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource;
use App\Modules\Shared\Application\Services\StorefrontText;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewBookingIntervention extends ViewRecord
{
    protected static string $resource = AdminBookingInterventionResource::class;

    public function infolist(Infolist $schema): Infolist
    {
        return $schema->schema([

            // ── Hero: two cards side-by-side ───────────────────────────────
            Grid::make(['default' => 1, 'md' => 12])
                ->columnSpanFull()
                ->schema([
                    Section::make(__('booking.intervention.sections.summary'))
                        ->icon('heroicon-o-clipboard-document-list')
                        ->columns(2)
                        ->columnSpan(['default' => 1, 'md' => 8])
                        ->schema([
                            TextEntry::make('reference_no')
                                ->label(__('booking.intervention.columns.reference'))
                                ->icon('heroicon-o-hashtag')
                                ->copyable()
                                ->weight('bold')
                                ->size('lg'),

                            TextEntry::make('customer.name')
                                ->label(__('booking.intervention.columns.customer'))
                                ->icon('heroicon-o-user')
                                ->default(fn ($record): string => '#'.$record->customer_id),

                            TextEntry::make('event_starts_at')
                                ->label(__('booking.intervention.columns.event_starts_at'))
                                ->icon('heroicon-o-calendar-days')
                                ->dateTime(),

                            TextEntry::make('event_ends_at')
                                ->label(__('booking.intervention.columns.event_ends_at'))
                                ->icon('heroicon-o-calendar')
                                ->dateTime(),

                            TextEntry::make('guest_count')
                                ->label(__('booking.intervention.columns.guest_count'))
                                ->icon('heroicon-o-user-group'),

                            TextEntry::make('submitted_at')
                                ->label(__('booking.intervention.columns.submitted_at'))
                                ->icon('heroicon-o-paper-airplane')
                                ->dateTime(),
                        ]),

                    Section::make(__('booking.intervention.sections.status'))
                        ->icon('heroicon-o-signal')
                        ->columnSpan(['default' => 1, 'md' => 4])
                        ->schema([
                            TextEntry::make('lifecycle_status')
                                ->label(__('booking.intervention.columns.lifecycle_status'))
                                ->badge()
                                ->color(fn ($state): string => match (is_string($state) ? $state : ($state?->value ?? '')) {
                                    'submitted' => 'info',
                                    'vendor_review' => 'warning',
                                    'customer_review' => 'primary',
                                    'confirmed' => 'success',
                                    default => 'gray',
                                }),

                            TextEntry::make('payment_status')
                                ->label(__('booking.intervention.columns.payment_status'))
                                ->badge()
                                ->color(fn ($state): string => match (is_string($state) ? $state : ($state?->value ?? '')) {
                                    'paid', 'captured' => 'success',
                                    'partially_paid', 'authorized' => 'info',
                                    'unpaid' => 'warning',
                                    'refunded', 'partially_refunded' => 'gray',
                                    'failed' => 'danger',
                                    default => 'gray',
                                }),

                            TextEntry::make('fulfillment_status')
                                ->label(__('booking.intervention.columns.fulfillment_status'))
                                ->badge()
                                ->color(fn ($state): string => match (is_string($state) ? $state : ($state?->value ?? '')) {
                                    'completed', 'delivered' => 'success',
                                    'in_progress', 'in_preparation', 'setup' => 'info',
                                    'not_started' => 'gray',
                                    'cancelled', 'failed' => 'danger',
                                    default => 'gray',
                                }),

                            TextEntry::make('total_minor')
                                ->label(__('booking.intervention.columns.total'))
                                ->icon('heroicon-o-banknotes')
                                ->money('EGP', divideBy: 100)
                                ->weight('bold')
                                ->size('lg'),
                        ]),
                ]),

            // ── Tabs: detail surfaces ──────────────────────────────────────
            Tabs::make('details')
                ->columnSpanFull()
                ->tabs([
                    Tab::make(__('booking.intervention.sections.vendors'))
                        ->icon('heroicon-o-building-storefront')
                        ->badge(fn ($record): int => $record->vendors()->count())
                        ->schema([
                            Section::make(__('booking.intervention.sections.vendors'))
                                ->icon('heroicon-o-building-storefront')
                                ->compact()
                                ->schema([
                                    RepeatableEntry::make('vendors')
                                        ->label('')
                                        ->contained(false)
                                        ->schema([
                                            Grid::make(4)->schema([
                                                TextEntry::make('vendor.business_name')
                                                    ->label(__('booking.intervention.columns.vendor'))
                                                    ->icon('heroicon-o-building-storefront')
                                                    ->weight('semibold')
                                                    ->getStateUsing(fn ($record): string => $record->vendor === null
                                                        ? '—'
                                                        : (app(StorefrontText::class)->translation($record->vendor, 'business_name') ?: '—'))
                                                    ->default('—'),

                                                TextEntry::make('sub_status')
                                                    ->label(__('booking.intervention.columns.sub_status'))
                                                    ->badge()
                                                    ->color(fn ($state): string => match (is_string($state) ? $state : ($state?->value ?? '')) {
                                                        'pending' => 'warning',
                                                        'accepted' => 'success',
                                                        'rejected' => 'danger',
                                                        default => 'gray',
                                                    }),

                                                TextEntry::make('response_deadline')
                                                    ->label(__('booking.intervention.columns.response_deadline'))
                                                    ->icon('heroicon-o-clock')
                                                    ->dateTime()
                                                    ->placeholder('—'),

                                                TextEntry::make('responded_at')
                                                    ->label(__('booking.intervention.columns.responded_at'))
                                                    ->icon('heroicon-o-check-circle')
                                                    ->dateTime()
                                                    ->placeholder('—'),
                                            ]),
                                        ]),
                                ]),

                            Section::make(__('booking.intervention.sections.modifications'))
                                ->icon('heroicon-o-pencil-square')
                                ->collapsible()
                                ->compact()
                                ->schema([
                                    RepeatableEntry::make('pendingModifications')
                                        ->label('')
                                        ->contained(false)
                                        ->schema([
                                            Grid::make(3)->schema([
                                                TextEntry::make('proposal_kind')
                                                    ->label(__('booking.intervention.columns.proposal_kind'))
                                                    ->badge()
                                                    ->color('info'),

                                                TextEntry::make('status')
                                                    ->label(__('booking.intervention.columns.status'))
                                                    ->badge()
                                                    ->color('warning'),

                                                TextEntry::make('created_at')
                                                    ->label(__('booking.intervention.columns.proposed_at'))
                                                    ->icon('heroicon-o-clock')
                                                    ->since(),
                                            ]),
                                        ]),
                                ]),
                        ]),

                    Tab::make(__('booking.intervention.sections.state_transitions'))
                        ->icon('heroicon-o-arrows-right-left')
                        ->schema([
                            Section::make(__('booking.intervention.sections.state_transitions'))
                                ->icon('heroicon-o-arrows-right-left')
                                ->compact()
                                ->schema([
                                    RepeatableEntry::make('stateTransitions')
                                        ->label('')
                                        ->contained(false)
                                        ->schema([
                                            Grid::make(4)->schema([
                                                TextEntry::make('from_state')
                                                    ->label(__('booking.intervention.columns.from_state'))
                                                    ->badge()
                                                    ->color('gray')
                                                    ->default('—'),

                                                TextEntry::make('to_state')
                                                    ->label(__('booking.intervention.columns.to_state'))
                                                    ->badge()
                                                    ->color('primary'),

                                                TextEntry::make('actor_type')
                                                    ->label(__('booking.intervention.columns.actor_type'))
                                                    ->icon('heroicon-o-user-circle')
                                                    ->default('—'),

                                                TextEntry::make('created_at')
                                                    ->label(__('booking.intervention.columns.transitioned_at'))
                                                    ->icon('heroicon-o-clock')
                                                    ->since(),
                                            ]),
                                        ]),
                                ]),

                            Section::make(__('booking.intervention.sections.intervention_history'))
                                ->icon('heroicon-o-shield-exclamation')
                                ->compact()
                                ->schema([
                                    RepeatableEntry::make('adminInterventions')
                                        ->label('')
                                        ->contained(false)
                                        ->schema([
                                            Grid::make(3)->schema([
                                                TextEntry::make('intervention_type')
                                                    ->label(__('booking.intervention.columns.intervention_type'))
                                                    ->badge()
                                                    ->color('danger'),

                                                TextEntry::make('admin.name')
                                                    ->label(__('booking.intervention.columns.admin'))
                                                    ->icon('heroicon-o-user-circle')
                                                    ->default('—'),

                                                TextEntry::make('created_at')
                                                    ->label(__('booking.intervention.columns.intervened_at'))
                                                    ->icon('heroicon-o-clock')
                                                    ->since(),
                                            ]),

                                            TextEntry::make('reason')
                                                ->label(__('booking.intervention.columns.reason'))
                                                ->prose()
                                                ->columnSpanFull(),
                                        ]),
                                ]),
                        ]),

                    Tab::make(__('booking.intervention.sections.payments'))
                        ->icon('heroicon-o-banknotes')
                        ->badge(fn ($record): int => $record->payments()->count())
                        ->schema([
                            Section::make(__('booking.intervention.sections.payments'))
                                ->icon('heroicon-o-banknotes')
                                ->compact()
                                ->schema([
                                    RepeatableEntry::make('payments')
                                        ->label('')
                                        ->contained(false)
                                        ->schema([
                                            Grid::make(4)->schema([
                                                TextEntry::make('gateway')
                                                    ->label(__('booking.intervention.columns.gateway'))
                                                    ->icon('heroicon-o-credit-card')
                                                    ->weight('semibold'),

                                                TextEntry::make('amount_minor')
                                                    ->label(__('booking.intervention.columns.amount'))
                                                    ->money('EGP', divideBy: 100)
                                                    ->weight('bold'),

                                                TextEntry::make('status')
                                                    ->label(__('booking.intervention.columns.status'))
                                                    ->badge()
                                                    ->color(fn ($state): string => match (is_string($state) ? $state : ($state?->value ?? '')) {
                                                        'succeeded', 'captured' => 'success',
                                                        'pending', 'authorized' => 'warning',
                                                        'failed', 'voided' => 'danger',
                                                        default => 'gray',
                                                    }),

                                                TextEntry::make('created_at')
                                                    ->label(__('booking.intervention.columns.paid_at'))
                                                    ->icon('heroicon-o-clock')
                                                    ->dateTime(),
                                            ]),
                                        ]),
                                ]),

                            Section::make(__('booking.intervention.sections.customer_notes'))
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->collapsible()
                                ->compact()
                                ->schema([
                                    RepeatableEntry::make('customerNotes')
                                        ->label('')
                                        ->contained(false)
                                        ->schema([
                                            TextEntry::make('note')
                                                ->label('')
                                                ->prose()
                                                ->columnSpanFull(),
                                        ]),
                                ]),
                        ]),
                ]),
        ]);
    }
}

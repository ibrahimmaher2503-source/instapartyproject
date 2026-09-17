<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\CustomerResource\Pages;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Application\Actions\AdminUpdateCustomerProfileAction;
use App\Modules\Identity\Application\Actions\ForceLogoutCustomerAction;
use App\Modules\Identity\Application\Actions\SuspendCustomerAction;
use App\Modules\Identity\Application\Actions\UnsuspendCustomerAction;
use App\Modules\Identity\Application\DTOs\AdminUpdateCustomerDTO;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Resources\CustomerResource;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\ModelStates\State;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function infolist(Infolist $schema): Infolist
    {
        return $infolist->schema([
            Tabs::make(__('identity.customer'))
                ->tabs([
                    Tab::make(__('identity.tabs.overview'))
                        ->schema($this->overviewTab()),

                    Tab::make(__('identity.tabs.bookings'))
                        ->schema($this->bookingsTab()),

                    Tab::make(__('identity.tabs.reviews'))
                        ->schema($this->reviewsTab()),

                    Tab::make(__('identity.tabs.wallet'))
                        ->schema($this->walletTab()),

                    Tab::make(__('identity.tabs.addresses'))
                        ->schema($this->addressesTab()),

                    Tab::make(__('identity.tabs.activity'))
                        ->schema($this->activityTab()),
                ])
                ->columnSpanFull(),
        ]);
    }

    /** @return array<int, mixed> */
    private function overviewTab(): array
    {
        return [
            Section::make(__('identity.sections.identity'))
                ->icon('heroicon-o-identification')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('name')
                        ->label(__('identity.columns.name'))
                        ->icon('heroicon-m-user')
                        ->weight(FontWeight::SemiBold),
                    TextEntry::make('email')
                        ->label(__('identity.columns.email'))
                        ->icon('heroicon-m-envelope')
                        ->copyable(),
                    TextEntry::make('phone_e164')
                        ->label(__('identity.columns.phone'))
                        ->icon('heroicon-m-phone')
                        ->placeholder('—')
                        ->copyable(),
                    TextEntry::make('status')
                        ->label(__('identity.columns.status'))
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'active' => 'success',
                            'suspended' => 'danger',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                    TextEntry::make('preferred_locale')
                        ->label(__('identity.fields.locale'))
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn (?string $state): string => match (strtolower((string) $state)) {
                            'ar' => 'العربية',
                            'en' => 'English',
                            default => '—',
                        }),
                    TextEntry::make('last_login_at')
                        ->label(__('identity.fields.last_login_at'))
                        ->icon('heroicon-m-clock')
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('created_at')
                        ->label(__('identity.fields.joined_at'))
                        ->icon('heroicon-m-calendar')
                        ->dateTime(),
                    TextEntry::make('customerProfile.date_of_birth')
                        ->label(__('identity.columns.date_of_birth'))
                        ->icon('heroicon-m-cake')
                        ->date()
                        ->placeholder('—'),
                    TextEntry::make('customerProfile.gender')
                        ->label(__('identity.columns.gender'))
                        ->badge()
                        ->color('gray')
                        ->placeholder('—')
                        ->formatStateUsing(fn (mixed $state): string => match ((string) $state) {
                            'male' => __('identity.gender.male'),
                            'female' => __('identity.gender.female'),
                            'prefer_not_to_say' => __('identity.gender.prefer_not_to_say'),
                            default => '—',
                        }),
                    IconEntry::make('customerProfile.accepts_marketing')
                        ->label(__('identity.columns.accepts_marketing'))
                        ->boolean(),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private function bookingsTab(): array
    {
        /** @var User $record */
        $record = $this->record;

        $bookings = Booking::query()
            ->where('customer_id', $record->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        if ($bookings->isEmpty()) {
            return [
                Section::make()
                    ->schema([
                        TextEntry::make('no_bookings')
                            ->state(__('identity.empty_states.no_bookings'))
                            ->icon('heroicon-o-information-circle')
                            ->color('gray')
                            ->hiddenLabel(),
                    ]),
            ];
        }

        $items = $bookings->map(fn (Booking $b): array => [
            'reference' => $b->public_id,
            'created_at' => $b->created_at,
            'lifecycle' => $b->lifecycle_status instanceof State
                ? $b->lifecycle_status::getMorphClass()
                : (string) $b->lifecycle_status,
            'payment' => $b->payment_status instanceof State
                ? $b->payment_status::getMorphClass()
                : (string) $b->payment_status,
            'fulfillment' => $b->fulfillment_status instanceof BackedEnum
                ? $b->fulfillment_status->value
                : (string) $b->fulfillment_status,
            'total_minor' => $b->total_minor,
        ])->all();

        return [
            Section::make(__('identity.tabs.bookings'))
                ->icon('heroicon-o-shopping-bag')
                ->description(trans_choice('identity.misc.bookings_count', $bookings->count(), ['count' => $bookings->count()]))
                ->schema([
                    RepeatableEntry::make('bookings_list')
                        ->state($items)
                        ->hiddenLabel()
                        ->contained(false)
                        ->columnSpanFull()
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 5])->schema([
                                TextEntry::make('reference')
                                    ->label(__('identity.columns.reference'))
                                    ->icon('heroicon-m-hashtag')
                                    ->weight(FontWeight::SemiBold)
                                    ->copyable(),
                                TextEntry::make('created_at')
                                    ->label(__('identity.columns.created_at'))
                                    ->dateTime(),
                                TextEntry::make('lifecycle')
                                    ->label(__('identity.columns.lifecycle_status'))
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'completed', 'confirmed' => 'success',
                                        'submitted', 'in_negotiation' => 'info',
                                        'pending', 'pending_payment' => 'warning',
                                        'cancelled', 'rejected', 'expired' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('payment')
                                    ->label(__('identity.columns.payment_status'))
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid', 'captured' => 'success',
                                        'partially_paid', 'authorized' => 'info',
                                        'unpaid' => 'warning',
                                        'failed' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('total_minor')
                                    ->label(__('identity.columns.total'))
                                    ->money('EGP', divideBy: 100)
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),
                            ]),
                        ]),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private function reviewsTab(): array
    {
        /** @var User $record */
        $record = $this->record;

        $serviceReviews = ServiceReview::query()
            ->where('user_id', $record->id)
            ->orderByDesc('created_at')
            ->get();

        $vendorReviews = VendorReview::query()
            ->where('user_id', $record->id)
            ->orderByDesc('created_at')
            ->get();

        if ($serviceReviews->isEmpty() && $vendorReviews->isEmpty()) {
            return [
                Section::make()
                    ->schema([
                        TextEntry::make('no_reviews')
                            ->state(__('identity.empty_states.no_reviews'))
                            ->icon('heroicon-o-information-circle')
                            ->color('gray')
                            ->hiddenLabel(),
                    ]),
            ];
        }

        $items = collect();
        $locale = app()->getLocale();
        foreach ($serviceReviews as $r) {
            $items->push([
                'kind' => 'service',
                'target' => 'Service #'.$r->service_id,
                'rating' => $r->rating,
                'body' => mb_substr((string) ($r->body[$locale] ?? $r->body['en'] ?? ''), 0, 280),
                'created_at' => $r->created_at,
            ]);
        }
        foreach ($vendorReviews as $r) {
            $items->push([
                'kind' => 'vendor',
                'target' => 'Vendor #'.$r->vendor_profile_id,
                'rating' => $r->rating,
                'body' => mb_substr((string) ($r->body[$locale] ?? $r->body['en'] ?? ''), 0, 280),
                'created_at' => $r->created_at,
            ]);
        }

        return [
            Section::make(__('identity.tabs.reviews'))
                ->icon('heroicon-o-star')
                ->description(trans_choice('identity.misc.reviews_count', $items->count(), ['count' => $items->count()]))
                ->schema([
                    RepeatableEntry::make('reviews_list')
                        ->state($items->sortByDesc('created_at')->values()->all())
                        ->hiddenLabel()
                        ->contained(false)
                        ->columnSpanFull()
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 4])->schema([
                                TextEntry::make('kind')
                                    ->label(__('identity.columns.kind'))
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'service' ? 'info' : 'warning'),
                                TextEntry::make('target')
                                    ->label(__('identity.columns.target'))
                                    ->weight(FontWeight::SemiBold),
                                TextEntry::make('rating')
                                    ->label(__('identity.columns.rating'))
                                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                                    ->color('warning'),
                                TextEntry::make('created_at')
                                    ->label(__('identity.columns.created_at'))
                                    ->dateTime(),
                            ]),
                            TextEntry::make('body')
                                ->label(__('identity.columns.body'))
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ]),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private function walletTab(): array
    {
        /** @var User $record */
        $record = $this->record;

        $balances = DB::table('loyalty_ledger')
            ->join('loyalty_programs', 'loyalty_programs.id', '=', 'loyalty_ledger.loyalty_program_id')
            ->where('loyalty_ledger.user_id', $record->id)
            ->groupBy('loyalty_ledger.loyalty_program_id', 'loyalty_programs.id')
            ->select([
                'loyalty_programs.id as program_id',
                DB::raw('SUM(loyalty_ledger.points) as balance'),
            ])
            ->get();

        if ($balances->isEmpty()) {
            return [
                TextEntry::make('no_wallet')
                    ->state(__('identity.empty_states.no_loyalty'))
                    ->hiddenLabel(),
            ];
        }

        $programIds = $balances->pluck('program_id');
        $programs = LoyaltyProgram::whereIn('id', $programIds)->get()->keyBy('id');

        $items = $balances->map(function (object $row) use ($programs): array {
            $program = $programs->get($row->program_id);
            $name = $program ? ($program->name[app()->getLocale()] ?? $program->name['en'] ?? 'Program #'.$row->program_id) : 'Program #'.$row->program_id;

            return ['program' => $name, 'points' => (int) $row->balance];
        })->all();

        return [
            Section::make(__('identity.tabs.wallet'))
                ->icon('heroicon-o-gift')
                ->schema([
                    RepeatableEntry::make('wallet_list')
                        ->state($items)
                        ->hiddenLabel()
                        ->contained(false)
                        ->columnSpanFull()
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextEntry::make('program')
                                    ->label(__('identity.columns.program'))
                                    ->icon('heroicon-m-trophy')
                                    ->weight(FontWeight::SemiBold),
                                TextEntry::make('points')
                                    ->label(__('identity.columns.points'))
                                    ->badge()
                                    ->color('success')
                                    ->formatStateUsing(fn (int $state): string => number_format($state).' pts'),
                            ]),
                        ]),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private function addressesTab(): array
    {
        /** @var User $record */
        $record = $this->record;

        $addresses = $record->customerAddresses()->with('city')->get();

        if ($addresses->isEmpty()) {
            return [
                TextEntry::make('no_addresses')
                    ->state(__('identity.empty_states.no_addresses'))
                    ->hiddenLabel(),
            ];
        }

        $locale = app()->getLocale();
        $items = $addresses->map(function (object $addr) use ($locale): array {
            $city = $addr->city?->name;
            $cityLabel = is_array($city) ? ($city[$locale] ?? $city['en'] ?? '—') : ($city ?? '—');
            $line = is_array($addr->address_line)
                ? ($addr->address_line[$locale] ?? $addr->address_line['en'] ?? '')
                : (string) $addr->address_line;

            return [
                'label' => (string) $addr->label,
                'address_line' => $line,
                'city' => $cityLabel,
                'is_default' => (bool) $addr->is_default,
            ];
        })->all();

        return [
            Section::make(__('identity.tabs.addresses'))
                ->icon('heroicon-o-map-pin')
                ->schema([
                    RepeatableEntry::make('addresses_list')
                        ->state($items)
                        ->hiddenLabel()
                        ->contained(false)
                        ->columnSpanFull()
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 4])->schema([
                                TextEntry::make('label')
                                    ->label(__('identity.columns.label'))
                                    ->icon('heroicon-m-tag')
                                    ->weight(FontWeight::SemiBold),
                                TextEntry::make('address_line')
                                    ->label(__('identity.columns.address_line'))
                                    ->placeholder('—')
                                    ->columnSpan(2),
                                TextEntry::make('city')
                                    ->label(__('identity.columns.city'))
                                    ->icon('heroicon-m-building-office-2'),
                                IconEntry::make('is_default')
                                    ->label(__('identity.columns.is_default'))
                                    ->boolean()
                                    ->columnSpanFull(),
                            ]),
                        ]),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private function activityTab(): array
    {
        /** @var User $record */
        $record = $this->record;

        $entries = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $record->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        if ($entries->isEmpty()) {
            return [
                TextEntry::make('no_activity')
                    ->state(__('identity.empty_states.no_activity'))
                    ->hiddenLabel(),
            ];
        }

        $items = $entries->map(fn (Activity $entry): array => [
            'description' => (string) $entry->description,
            'causer' => $entry->causer?->name ?? 'system',
            'created_at' => $entry->created_at,
        ])->all();

        return [
            Section::make(__('identity.tabs.activity'))
                ->icon('heroicon-o-clock')
                ->schema([
                    RepeatableEntry::make('activity_list')
                        ->state($items)
                        ->hiddenLabel()
                        ->contained(false)
                        ->columnSpanFull()
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 3])->schema([
                                TextEntry::make('description')
                                    ->label(__('identity.columns.description'))
                                    ->weight(FontWeight::SemiBold)
                                    ->columnSpan(2),
                                TextEntry::make('created_at')
                                    ->label(__('identity.columns.created_at'))
                                    ->dateTime()
                                    ->since(),
                                TextEntry::make('causer')
                                    ->label(__('identity.columns.actor'))
                                    ->icon('heroicon-m-user')
                                    ->color('gray')
                                    ->columnSpanFull(),
                            ]),
                        ]),
                ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->editProfileAction(),
            $this->suspendAction(),
            $this->unsuspendAction(),
            $this->forceLogoutAction(),
        ];
    }

    private function editProfileAction(): Action
    {
        return Action::make('edit_profile')
            ->label(__('identity.actions.edit_profile'))
            ->icon('heroicon-o-pencil')
            ->color('primary')
            ->form([
                TextInput::make('name')
                    ->label(__('identity.fields.name'))
                    ->default(fn () => $this->record->name)
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone_e164')
                    ->label(__('identity.fields.phone'))
                    ->default(fn () => $this->record->phone_e164)
                    ->nullable()
                    ->tel()
                    ->helperText('E.164 format: +201001234567'),
            ])
            ->action(function (array $data): void {
                $dto = new AdminUpdateCustomerDTO(
                    name: $data['name'],
                    phoneE164: $data['phone_e164'] ?: null,
                );

                app(AdminUpdateCustomerProfileAction::class)->execute($this->record, $dto);

                Notification::make()
                    ->title(__('identity.notifications.profile_updated_by_admin'))
                    ->success()
                    ->send();

                $this->refreshFormData(['name', 'phone_e164']);
            })
            ->visible(fn () => auth()->user()?->can('update_customer_profile'));
    }

    private function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label(__('identity.actions.suspend_customer'))
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn () => __('identity.modals.suspend_customer', ['name' => $this->record->name]))
            ->action(function (): void {
                app(SuspendCustomerAction::class)->execute($this->record);

                Notification::make()
                    ->title(__('identity.notifications.customer_suspended'))
                    ->success()
                    ->send();

                $this->redirect(CustomerResource::getUrl('view', ['record' => $this->record]));
            })
            ->visible(fn () => $this->record->status === 'active'
                && $this->record->id !== auth()->id()
                && auth()->user()?->can('suspend_customer'));
    }

    private function unsuspendAction(): Action
    {
        return Action::make('unsuspend')
            ->label(__('identity.actions.unsuspend_customer'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (): void {
                app(UnsuspendCustomerAction::class)->execute($this->record);

                Notification::make()
                    ->title(__('identity.notifications.customer_unsuspended'))
                    ->success()
                    ->send();

                $this->redirect(CustomerResource::getUrl('view', ['record' => $this->record]));
            })
            ->visible(fn () => $this->record->status === 'suspended'
                && auth()->user()?->can('suspend_customer'));
    }

    private function forceLogoutAction(): Action
    {
        return Action::make('force_logout')
            ->label(__('identity.actions.force_logout'))
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(fn () => __('identity.modals.force_logout_customer', ['name' => $this->record->name]))
            ->action(function (): void {
                app(ForceLogoutCustomerAction::class)->execute($this->record);

                Notification::make()
                    ->title(__('identity.notifications.customer_logged_out'))
                    ->success()
                    ->send();
            })
            ->visible(fn () => auth()->user()?->can('force_logout_customer'));
    }
}

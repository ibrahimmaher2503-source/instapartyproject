<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\Pages;

use App\Modules\Booking\Application\Actions\AddItemToBookingAction;
use App\Modules\Booking\Application\Actions\CreateBookingDraftAction;
use App\Modules\Booking\Application\Actions\SubmitBookingAction;
use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Application\DTOs\CreateBookingDraftDTO;
use App\Modules\Booking\Application\DTOs\SubmitBookingDTO;
use App\Modules\Booking\Filament\Resources\BookingResource;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Services\StorefrontText;
use Carbon\Carbon;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    public function getTitle(): string
    {
        return __('booking.actions.create_booking');
    }

    public function form(Form $schema): Form
    {
        return $schema->components([
            Wizard::make([
                Step::make(__('booking.wizard.step_event'))
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Select::make('customer_id')
                            ->label(__('booking.create.customer'))
                            ->options(function (string $search = ''): array {
                                return User::query()
                                    ->role('customer')
                                    ->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")
                                            ->orWhere('phone_e164', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (User $u) => [
                                        $u->id => "{$u->name} ({$u->phone_e164})",
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->required(),

                        Select::make('occasion_id')
                            ->label(__('booking.create.occasion'))
                            ->options(function (): array {
                                return Occasion::query()
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->mapWithKeys(fn (Occasion $o) => [
                                        $o->id => $o->getTranslation('name', app()->getLocale(), false)
                                            ?: $o->getTranslation('name', 'en', false),
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->required(),

                        DateTimePicker::make('event_starts_at')
                            ->label(__('booking.create.event_starts_at'))
                            ->required()
                            ->minDate(now()->addHour())
                            ->seconds(false),

                        DateTimePicker::make('event_ends_at')
                            ->label(__('booking.create.event_ends_at'))
                            ->required()
                            ->after('event_starts_at')
                            ->seconds(false),

                        TextInput::make('guest_count')
                            ->label(__('booking.create.guest_count'))
                            ->numeric()
                            ->minValue(1)
                            ->nullable(),
                    ])
                    ->columns(2),

                Step::make(__('booking.wizard.step_address'))
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Select::make('address_city_id')
                            ->label(__('booking.create.city'))
                            ->options(function (): array {
                                return City::query()
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (City $c) => [
                                        $c->id => is_array($c->name)
                                            ? ($c->name[app()->getLocale()] ?? $c->name['en'] ?? $c->id)
                                            : $c->name,
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('address_line')
                            ->label(__('booking.create.address_line'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('address_building')
                            ->label(__('booking.create.address_building'))
                            ->maxLength(80),

                        TextInput::make('address_floor')
                            ->label(__('booking.create.address_floor'))
                            ->maxLength(20),

                        TextInput::make('address_apartment')
                            ->label(__('booking.create.address_apartment'))
                            ->maxLength(20),

                        TextInput::make('address_landmark')
                            ->label(__('booking.create.address_landmark'))
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('recipient_name')
                            ->label(__('booking.create.recipient_name'))
                            ->required()
                            ->maxLength(120),

                        TextInput::make('recipient_phone_e164')
                            ->label(__('booking.create.recipient_phone'))
                            ->required()
                            ->tel()
                            ->regex('/^\+[1-9]\d{7,14}$/')
                            ->placeholder('+201234567890'),
                    ])
                    ->columns(2),

                Step::make(__('booking.wizard.step_service'))
                    ->icon('heroicon-o-shopping-bag')
                    ->schema([
                        Select::make('service_id')
                            ->label(__('booking.create.service'))
                            ->options(function (): array {
                                return Service::query()
                                    ->where('status', 'published')
                                    ->with('vendor')
                                    ->get()
                                    ->mapWithKeys(function (Service $s) {
                                        $locale = app()->getLocale();
                                        $name = is_array($s->name)
                                            ? ($s->name[$locale] ?? $s->name['en'] ?? '#'.$s->id)
                                            : ($s->name ?? '#'.$s->id);
                                        $vendor = $s->vendor === null
                                            ? ''
                                            : app(StorefrontText::class)->translation($s->vendor, 'business_name', $locale);

                                        return [$s->id => "{$name} — {$vendor}"];
                                    })
                                    ->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('quantity')
                            ->label(__('booking.create.quantity'))
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1),
                    ])
                    ->columns(2),
            ])
                ->columnSpanFull()
                ->submitAction(
                    Action::make('submit')
                        ->label(__('booking.wizard.submit'))
                        ->submit('create')
                        ->color('primary')
                ),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $eventStartsAt = Carbon::parse($data['event_starts_at']);
            $eventEndsAt = Carbon::parse($data['event_ends_at']);

            $draftDto = new CreateBookingDraftDTO(
                customerId: (int) $data['customer_id'],
                occasionId: (int) $data['occasion_id'],
                eventStartsAt: $eventStartsAt,
                eventEndsAt: $eventEndsAt,
                guestCount: isset($data['guest_count']) ? (int) $data['guest_count'] : null,
                theme: null,
                celebrantName: null,
                celebrantDob: null,
                celebrantGender: null,
                addressCityId: (int) $data['address_city_id'],
                addressLine: $data['address_line'],
                addressBuilding: $data['address_building'] ?? null,
                addressFloor: $data['address_floor'] ?? null,
                addressApartment: $data['address_apartment'] ?? null,
                addressLandmark: $data['address_landmark'] ?? null,
                addressLatitude: null,
                addressLongitude: null,
                recipientName: $data['recipient_name'],
                recipientPhoneE164: $data['recipient_phone_e164'],
            );

            $booking = app(CreateBookingDraftAction::class)->execute($draftDto);

            $addItemDto = new AddBookingItemDTO(
                bookingId: $booking->id,
                customerId: (int) $data['customer_id'],
                serviceId: (int) $data['service_id'],
                quantity: (int) $data['quantity'],
                effectiveStartsAt: $eventStartsAt,
                effectiveEndsAt: $eventEndsAt,
                customizationData: null,
            );

            app(AddItemToBookingAction::class)->execute($addItemDto);

            $submitDto = new SubmitBookingDTO(
                bookingId: $booking->id,
                customerId: (int) $data['customer_id'],
                idempotencyKey: (string) Str::uuid(),
            );

            app(SubmitBookingAction::class)->execute($submitDto);

            Notification::make()
                ->title(__('booking.create.success'))
                ->success()
                ->send();

            return $booking->fresh();
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('booking.create.error'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return BookingResource::getUrl('view', ['record' => $this->record]);
    }
}

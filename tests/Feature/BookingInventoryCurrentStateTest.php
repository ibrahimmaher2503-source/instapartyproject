<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AddItemToBookingAction;
use App\Modules\Booking\Application\Actions\CustomerConfirmModifiedBookingAction;
use App\Modules\Booking\Application\Actions\SubmitBookingAction;
use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Application\DTOs\CustomerModificationDecisionDTO;
use App\Modules\Booking\Application\DTOs\ServiceReadDTO;
use App\Modules\Booking\Application\DTOs\SubmitBookingDTO;
use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Catalog\Infrastructure\Repositories\EloquentCatalogServiceReader;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('uses locked sale stock after the catalog reader returns its earlier snapshot', function (): void {
    $booking = Booking::factory()->draft()->create();
    $service = Service::factory()->sale()->published()->create([
        'base_price_minor' => 1000,
        'base_price_currency' => 'EGP',
    ]);
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 1]);
    $service->category->occasions()->attach($booking->occasion_id);

    app()->instance(CatalogServiceReader::class, new class($service->id) extends EloquentCatalogServiceReader
    {
        public function __construct(
            private readonly int $serviceId,
        ) {}

        public function findPublishedById(int $id): ?ServiceReadDTO
        {
            $snapshot = parent::findPublishedById($id);
            if ($id === $this->serviceId) {
                DB::table('service_sale_details')->where('service_id', $id)->update(['stock_quantity' => 0]);
            }

            return $snapshot;
        }
    });

    $status = null;
    try {
        app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
            bookingId: $booking->id,
            customerId: $booking->customer_id,
            serviceId: $service->id,
            quantity: 1,
            effectiveStartsAt: null,
            effectiveEndsAt: null,
            customizationData: null,
        ));
    } catch (HttpResponseException $exception) {
        $status = $exception->getResponse()->getStatusCode();
    } catch (HttpException $exception) {
        $status = $exception->getStatusCode();
    }

    expect($status)->toBe(409)
        ->and(DB::table('service_inventory_reservations')->where('service_id', $service->id)->count())->toBe(0);
});

it('enforces lead time for made-to-order sales without delaying ordinary stocked sales', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $tooSoonBooking = Booking::factory()->draft()->create([
        'customer_id' => $customer->id,
        'event_starts_at' => now()->addHour(),
    ]);
    $madeToOrder = Service::factory()->sale()->published()->create();
    $occasion = Occasion::factory()->active()->create();
    $madeToOrder->category->occasions()->attach($occasion->id);
    $tooSoonBooking->update(['occasion_id' => $occasion->id]);
    ServiceSaleDetail::factory()->create([
        'service_id' => $madeToOrder->id,
        'is_made_to_order' => true,
        'lead_time_hours' => 24,
        'stock_quantity' => 10,
    ]);

    $exception = null;
    try {
        app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
            bookingId: $tooSoonBooking->id,
            customerId: $customer->id,
            serviceId: $madeToOrder->id,
            quantity: 1,
            effectiveStartsAt: null,
            effectiveEndsAt: null,
            customizationData: null,
        ));
    } catch (HttpResponseException $caught) {
        $exception = $caught;
    }

    expect($exception?->getResponse()->getStatusCode())->toBe(422)
        ->and(DB::table('booking_items')->where('service_id', $madeToOrder->id)->exists())->toBeFalse()
        ->and(DB::table('service_inventory_reservations')->where('service_id', $madeToOrder->id)->exists())->toBeFalse();

    $ordinaryBooking = Booking::factory()->draft()->create([
        'customer_id' => $customer->id,
        'event_starts_at' => now()->addHour(),
    ]);
    $ordinarySale = Service::factory()->sale()->published()->create();
    $ordinarySale->category->occasions()->attach($occasion->id);
    $ordinaryBooking->update(['occasion_id' => $occasion->id]);
    ServiceSaleDetail::factory()->create([
        'service_id' => $ordinarySale->id,
        'is_made_to_order' => false,
        'lead_time_hours' => 24,
        'stock_quantity' => 10,
    ]);

    $item = app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
        bookingId: $ordinaryBooking->id,
        customerId: $customer->id,
        serviceId: $ordinarySale->id,
        quantity: 1,
        effectiveStartsAt: null,
        effectiveEndsAt: null,
        customizationData: null,
    ));

    expect($item->exists)->toBeTrue();
});

it('rechecks made-to-order lead time when a draft is submitted later', function (): void {
    $now = now()->startOfMinute();
    $this->travelTo($now);
    $customer = User::factory()->asCustomer()->create();
    $occasion = Occasion::factory()->active()->create();
    $booking = Booking::factory()->draft()->create([
        'customer_id' => $customer->id,
        'occasion_id' => $occasion->id,
        'event_starts_at' => $now->copy()->addHours(25),
    ]);
    $service = Service::factory()->sale()->published()->create();
    $service->category->occasions()->attach($occasion->id);
    ServiceSaleDetail::factory()->create([
        'service_id' => $service->id,
        'is_made_to_order' => true,
        'lead_time_hours' => 24,
        'stock_quantity' => 10,
    ]);

    app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
        bookingId: $booking->id,
        customerId: $customer->id,
        serviceId: $service->id,
        quantity: 1,
        effectiveStartsAt: null,
        effectiveEndsAt: null,
        customizationData: null,
    ));

    $this->travelTo($now->copy()->addHours(2));
    $status = null;
    try {
        app(SubmitBookingAction::class)->execute(new SubmitBookingDTO(
            bookingId: $booking->id,
            customerId: $customer->id,
            idempotencyKey: (string) Str::uuid(),
            requestContent: 'lead-time-regression',
        ));
    } catch (HttpException $exception) {
        $status = $exception->getStatusCode();
    }

    expect($status)->toBe(422)
        ->and($booking->fresh()->lifecycle_status)->toBeInstanceOf(DraftState::class);
});

it('uses current sale stock when a customer accepts a booking modification', function (): void {
    Event::fake();

    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => CustomerReviewState::class,
    ]);
    $bookingVendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'sub_status' => VendorSubStatus::Modified,
    ]);
    $service = Service::factory()->sale()->published()->create([
        'vendor_profile_id' => $vendor->id,
        'base_price_minor' => 1000,
    ]);
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 1]);
    $modification = BookingModification::factory()->create([
        'booking_vendor_id' => $bookingVendor->id,
        'proposed_by' => $vendor->user_id,
        'status' => ModificationStatus::Pending,
        'expires_at' => now()->addHour(),
    ]);
    BookingModificationItem::create([
        'booking_modification_id' => $modification->id,
        'change_kind' => ModificationChangeKind::Add,
        'payload' => [
            'service_id' => $service->id,
            'product_type' => 'sale',
            'unit_price_minor' => 1000,
            'quantity' => 1,
        ],
    ]);

    $stockChanged = false;
    DB::listen(function (QueryExecuted $query) use (&$stockChanged, $service): void {
        if (! $stockChanged && str_contains($query->sql, 'service_rental_details') && str_starts_with(strtolower($query->sql), 'select')) {
            $stockChanged = true;
            DB::table('service_sale_details')->where('service_id', $service->id)->update(['stock_quantity' => 0]);
        }
    });

    $status = null;
    try {
        app(CustomerConfirmModifiedBookingAction::class)->execute(new CustomerModificationDecisionDTO(
            $booking->id,
            $customer->id,
            $modification->public_id,
            'accepted',
            (string) Str::uuid(),
        ));
    } catch (HttpException $exception) {
        $status = $exception->getStatusCode();
    }

    expect($stockChanged)->toBeTrue()
        ->and($status)->toBe(409)
        ->and(DB::table('booking_items')->where('service_id', $service->id)->count())->toBe(0)
        ->and(DB::table('service_inventory_reservations')->where('service_id', $service->id)->count())->toBe(0);
});

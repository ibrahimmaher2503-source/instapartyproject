<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AddTransportationAdjustmentAction;
use App\Modules\Booking\Application\Actions\CancelBookingByCustomerAction;
use App\Modules\Booking\Database\Factories\BookingFactory;
use App\Modules\Booking\Database\Factories\BookingItemFactory;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingCompleted;
use App\Modules\Booking\Domain\Models\BookingAdjustment;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Application\Actions\CalculateLoyaltyPointsAction;
use App\Modules\Payments\Database\Factories\PaymentFactory;
use App\Modules\Shared\Domain\Models\StateTransition;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

it('shows one persisted admin transport line consistently in customer totals and both invoices', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $customer = User::factory()->asCustomer()->create();
    $admin = User::factory()->superAdmin()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = BookingFactory::new()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => 'active',
        'subtotal_minor' => 10_000,
        'delivery_total_minor' => 500,
        'total_minor' => 10_500,
    ]);
    $allocation = BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'subtotal_minor' => 10_000,
        'delivery_fee_minor' => 500,
    ]);

    $this->actingAs($admin, 'web');
    Livewire::test(ViewBooking::class, ['record' => $booking->public_id])
        ->callAction('addTransportationLine', data: [
            'amount' => '10.00',
            'source' => 'QA transport line',
            'source_reference' => 'QA-169-SAME-BOOKING',
        ])
        ->assertHasNoActionErrors();

    $adjustment = BookingAdjustment::query()
        ->where('booking_id', $booking->id)
        ->where('source_reference', 'QA-169-SAME-BOOKING')
        ->sole();
    expect($adjustment->amount_minor)->toBe(1_000)
        ->and($booking->fresh()->total_minor)->toBe(11_500)
        ->and(DB::table('audit_logs')->where('auditable_id', $adjustment->id)
            ->where('action', 'booking_transportation_adjustment_added')->count())->toBe(1);

    $replayed = app(AddTransportationAdjustmentAction::class)->execute(
        $booking->fresh(), $admin->id, 1_000, 'QA transport line', 'QA-169-SAME-BOOKING',
    );
    expect($replayed->is($adjustment))->toBeTrue()
        ->and(BookingAdjustment::query()->where('booking_id', $booking->id)->where('source_reference', 'QA-169-SAME-BOOKING')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'booking_transportation_adjustment_added')
            ->where('auditable_type', BookingAdjustment::class)->count())->toBe(1)
        ->and($booking->fresh()->total_minor)->toBe(11_500);

    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);
        try {
            app(AddTransportationAdjustmentAction::class)->execute(
                $booking->fresh(), $admin->id, 1_200, 'QA transport line', 'QA-169-SAME-BOOKING',
            );
            $this->fail('A transportation reference with a changed amount must be rejected.');
        } catch (ValidationException $exception) {
            expect($exception->errors()['source_reference'])
                ->toBe([__('booking::booking.validation.transport_reference_conflict')]);
        }
    }
    app()->setLocale('en');
    expect(fn () => app(AddTransportationAdjustmentAction::class)->execute(
        $booking->fresh(), $admin->id, 1_000, 'Changed source', 'QA-169-SAME-BOOKING',
    ))->toThrow(ValidationException::class);
    expect(BookingAdjustment::query()->where('booking_id', $booking->id)->where('source_reference', 'QA-169-SAME-BOOKING')->count())->toBe(1)
        ->and($booking->fresh()->total_minor)->toBe(11_500);

    $this->actingAs($customer, 'web')
        ->get(route('storefront.account.bookings.invoice', ['locale' => 'en', 'bookingPublicId' => $booking->public_id]))
        ->assertOk()
        ->assertViewHas('transportAdjustmentMinor', 1_000)
        ->assertViewHas('totalReconciliationMinor', 0);
    $this->actingAs($admin, 'web')
        ->get(route('admin.bookings.invoice', [$booking->public_id, 'lang' => 'en']))
        ->assertOk()
        ->assertSee('QA transport line');

    Sanctum::actingAs($customer);
    $this->getJson('/api/v1/customer/bookings/'.$booking->public_id)
        ->assertOk()
        ->assertJsonPath('data.total_minor', 11_500)
        ->assertJsonPath('data.transportation_adjustment_minor', 1_000);

    Sanctum::actingAs($vendor->user);
    $this->getJson('/api/v1/vendor/booking-vendors/'.$allocation->public_id)
        ->assertOk()
        ->assertJsonPath('data.subtotal_minor', 10_000)
        ->assertJsonPath('data.delivery_fee_minor', 500)
        ->assertJsonMissingPath('data.total_minor');
});

it('replays a blank-reference admin transport form once but gives a newly opened form a new reference', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    $booking = BookingFactory::new()->create(['lifecycle_status' => 'active']);
    $this->actingAs($admin, 'web');
    $page = Livewire::test(ViewBooking::class, ['record' => $booking->public_id]);
    $formData = [
        'amount' => '10.00',
        'source' => 'QA blank-reference line',
        'source_reference' => '   ',
    ];

    $page->mountAction('addTransportationLine')->assertActionMounted('addTransportationLine');
    $firstToken = $page->instance()->mountedActionsData[0]['submission_reference'] ?? null;
    expect($firstToken)->toBeString()->and(Str::isUlid($firstToken))->toBeTrue();
    $page->setActionData($formData)->callMountedAction()->assertHasNoActionErrors();

    // A retried request carries the original mounted modal state, including its token.
    $page->mountAction('addTransportationLine')->assertActionMounted('addTransportationLine');
    $page->setActionData([...$formData, 'submission_reference' => $firstToken])
        ->callMountedAction()->assertHasNoActionErrors();
    expect(BookingAdjustment::query()->where('booking_id', $booking->id)->count())->toBe(1)
        ->and($booking->fresh()->total_minor)->toBe(1_000)
        ->and(DB::table('audit_logs')->where('action', 'booking_transportation_adjustment_added')->count())->toBe(1);

    $page->mountAction('addTransportationLine')->assertActionMounted('addTransportationLine');
    $nextToken = $page->instance()->mountedActionsData[0]['submission_reference'] ?? null;
    expect($nextToken)->toBeString()
        ->and(Str::isUlid($nextToken))->toBeTrue()
        ->and($nextToken)->not->toBe($firstToken);
    $page->setActionData($formData)->callMountedAction()->assertHasNoActionErrors();

    expect(BookingAdjustment::query()->where('booking_id', $booking->id)->count())->toBe(2)
        ->and($booking->fresh()->total_minor)->toBe(2_000)
        ->and(DB::table('audit_logs')->where('action', 'booking_transportation_adjustment_added')->count())->toBe(2);
});

it('accepts a pending vendor request through the API and rejects its replay after confirming the booking', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $vendorUser = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
    $booking = BookingFactory::new()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => VendorReviewState::class,
    ]);
    $allocation = BookingVendorFactory::new()->pending()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'response_deadline' => now()->addHour(),
    ]);
    $key = (string) Str::uuid();

    Sanctum::actingAs($vendorUser);
    $this->getJson('/api/v1/vendor/booking-vendors')->assertOk()->assertJsonFragment(['public_id' => $allocation->public_id]);
    $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/vendor/booking-vendors/'.$allocation->public_id.'/accept', [])
        ->assertOk()
        ->assertJsonPath('data.sub_status', 'accepted')
        ->assertJsonPath('data.responded_at', fn ($value) => is_string($value));
    $respondedAt = $allocation->fresh()->responded_at;
    expect($respondedAt)->not->toBeNull()
        ->and($booking->fresh()->lifecycle_status->getValue())->toBe('confirmed')
        ->and(StateTransition::query()->where('transitionable_type', BookingVendor::class)
            ->where('transitionable_id', $allocation->id)->where('to_state', 'accepted')->count())->toBe(1);

    $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/vendor/booking-vendors/'.$allocation->public_id.'/accept', [])
        ->assertOk()
        ->assertJsonPath('data.sub_status', 'accepted');
    expect($allocation->fresh()->responded_at->equalTo($respondedAt))->toBeTrue()
        ->and(StateTransition::query()->where('transitionable_type', BookingVendor::class)
            ->where('transitionable_id', $allocation->id)->where('to_state', 'accepted')->count())->toBe(1);

    Sanctum::actingAs($customer);
    $this->getJson('/api/v1/customer/bookings/'.$booking->public_id)
        ->assertOk()
        ->assertJsonPath('data.lifecycle_status', 'confirmed')
        ->assertJsonPath('data.vendors_summary.accepted', 1);

    Sanctum::actingAs($vendorUser);
    $this->getJson('/api/v1/vendor/booking-vendors')->assertOk()->assertJsonMissing(['public_id' => $allocation->public_id]);
});

it('records vendor rejection once and blocks a vendor decision after customer cancellation', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $vendorUser = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
    $booking = BookingFactory::new()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => VendorReviewState::class,
    ]);
    $allocation = BookingVendorFactory::new()->pending()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'response_deadline' => now()->addHour(),
    ]);
    $reason = ['en' => 'Date unavailable.', 'ar' => 'التاريخ غير متاح.'];

    Sanctum::actingAs($vendorUser);
    $this->postJson('/api/v1/vendor/booking-vendors/'.$allocation->public_id.'/reject', ['rejection_reason' => $reason])
        ->assertOk()->assertJsonPath('data.sub_status', 'rejected');
    $respondedAt = $allocation->fresh()->responded_at;
    expect($allocation->fresh()->rejection_reason)->toBe($reason)
        ->and($respondedAt)->not->toBeNull()
        ->and($booking->fresh()->lifecycle_status->getValue())->toBe('cancelled')
        ->and(StateTransition::query()->where('transitionable_type', BookingVendor::class)
            ->where('transitionable_id', $allocation->id)->where('to_state', 'rejected')->count())->toBe(1);

    $this->postJson('/api/v1/vendor/booking-vendors/'.$allocation->public_id.'/reject', ['rejection_reason' => $reason])
        ->assertConflict();
    expect($allocation->fresh()->responded_at->equalTo($respondedAt))->toBeTrue()
        ->and(StateTransition::query()->where('transitionable_type', BookingVendor::class)
            ->where('transitionable_id', $allocation->id)->where('to_state', 'rejected')->count())->toBe(1);

    Sanctum::actingAs($customer);
    $this->getJson('/api/v1/customer/bookings/'.$booking->public_id)
        ->assertOk()
        ->assertJsonPath('data.lifecycle_status', 'cancelled')
        ->assertJsonPath('data.vendors_summary.rejected', 1);

    $cancelledBooking = BookingFactory::new()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => VendorReviewState::class,
    ]);
    $cancelledAllocation = BookingVendorFactory::new()->pending()->create([
        'booking_id' => $cancelledBooking->id,
        'vendor_profile_id' => $vendor->id,
        'response_deadline' => now()->addHour(),
    ]);
    app(CancelBookingByCustomerAction::class)
        ->execute($cancelledBooking, $customer->id, 'customer requested cancellation');

    Sanctum::actingAs($vendorUser);
    $this->postJson('/api/v1/vendor/booking-vendors/'.$cancelledAllocation->public_id.'/accept', [])
        ->assertConflict();
    expect($cancelledAllocation->fresh()->sub_status)->toBe(VendorSubStatus::Cancelled)
        ->and($cancelledAllocation->fresh()->responded_at)->not->toBeNull();
});

it('completes an accepted sale booking through the vendor item transition API', function (): void {
    Queue::fake();
    $vendorUser = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
    $booking = BookingFactory::new()->create([
        'lifecycle_status' => 'active',
        'payment_status' => 'paid',
        'total_minor' => 10_000,
    ]);
    $allocation = BookingVendorFactory::new()->accepted()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
    ]);
    $item = BookingItemFactory::new()->sale()->create([
        'booking_vendor_id' => $allocation->id,
        'item_status' => 'pending',
    ]);
    PaymentFactory::new()->captured()->create([
        'booking_id' => $booking->id,
        'user_id' => $booking->customer_id,
        'amount_minor' => 10_000,
        'amount_currency' => 'EGP',
    ]);

    Sanctum::actingAs($vendorUser);
    foreach (['preparing', 'ready', 'in_progress', 'completed'] as $lane) {
        $this->postJson('/api/v1/vendor/booking-items/'.$item->public_id.'/transition', [
            'lane' => $lane,
            'completion_note' => $lane === 'completed' ? 'Delivered in QA fixture.' : null,
        ])->assertOk();
    }

    expect($item->fresh()->item_status)->toBe('delivered')
        ->and($allocation->fresh()->sub_status)->toBe(VendorSubStatus::Completed)
        ->and($booking->fresh()->lifecycle_status->getValue())->toBe('completed');
});

it('passes every item from a completed booking to the loyalty listener without ambiguous through columns', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = BookingFactory::new()->create(['customer_id' => $customer->id]);
    $expectedItemIds = [];

    foreach (range(1, 2) as $index) {
        $vendor = VendorProfile::factory()->approved()->create();
        $allocation = BookingVendorFactory::new()->completed()->create([
            'booking_id' => $booking->id,
            'vendor_profile_id' => $vendor->id,
        ]);
        $expectedItemIds[] = BookingItemFactory::new()->sale()->create([
            'booking_vendor_id' => $allocation->id,
        ])->id;
    }

    $action = Mockery::mock(CalculateLoyaltyPointsAction::class);
    foreach ($expectedItemIds as $itemId) {
        $action->shouldReceive('execute')->once()->with($customer->id, $itemId, $booking->id)->andReturnNull();
    }

    app()->instance(CalculateLoyaltyPointsAction::class, $action);
    event(new BookingCompleted($booking));
});

<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Domain\States\PaymentStatus\PartiallyRefundedState;
use App\Modules\Shared\Domain\Models\StateTransition;
use Brick\Money\Money;
use Illuminate\Support\Str;

it('shows the customer cancellation reason and unpaid outcome without exposing admin notes', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $admin = User::factory()->asAdmin()->create();
    $booking = Booking::factory()->cancelled()->create([
        'customer_id' => $customer->id,
        'total_minor' => 90000,
    ]);
    $reason = 'QA journey test cleanup - force cancel';
    $intervention = BookingAdminIntervention::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $booking->id,
        'admin_id' => $admin->id,
        'intervention_type' => InterventionType::ForceCancel,
        'reason' => $reason,
        'before_state' => ['lifecycle_status' => 'vendor_review'],
        'after_state' => ['lifecycle_status' => LifecycleStatus::Cancelled->value],
    ]);
    BookingAdminIntervention::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $booking->id,
        'admin_id' => $admin->id,
        'intervention_type' => InterventionType::AdminNote,
        'reason' => 'Do not expose this admin note',
        'before_state' => [],
        'after_state' => [],
    ]);
    StateTransition::create([
        'transitionable_type' => Booking::class,
        'transitionable_id' => $booking->id,
        'from_state' => 'vendor_review',
        'to_state' => LifecycleStatus::Cancelled->value,
        'trigger_kind' => 'admin',
        'triggered_by' => $admin->id,
        'context' => ['intervention_id' => $intervention->id, 'reason' => $reason],
    ]);

    $moneyLocale = $locale === 'ar' ? 'ar_EG' : 'en_GB';
    $this->actingAs($customer)->get("/{$locale}/account/bookings/{$booking->public_id}")
        ->assertOk()
        ->assertSee(__('account.booking_total', [], $locale))
        ->assertSee(__('account.amount_captured', [], $locale))
        ->assertSee(__('account.amount_refunded', [], $locale))
        ->assertSee($booking->total->formatTo($moneyLocale))
        ->assertSee(Money::ofMinor(0, 'EGP')->formatTo($moneyLocale))
        ->assertSee(__('account.cancellation_reason', [], $locale))
        ->assertSee($reason)
        ->assertSee(__('account.refund_guidance.no_payment', [], $locale))
        ->assertDontSee('Do not expose this admin note');
})->with(['en', 'ar']);

it('distinguishes an in-progress refund from the booking total and captured amount', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->cancelled()->create([
        'customer_id' => $customer->id,
        'total_minor' => 90000,
    ]);
    $payment = Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'user_id' => $customer->id,
        'amount_minor' => 90000,
    ]);
    Refund::factory()->create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'initiated_by' => $customer->id,
        'amount_minor' => 90000,
        'status' => RefundStatus::Pending,
    ]);

    $moneyLocale = $locale === 'ar' ? 'ar_EG' : 'en_GB';
    $this->actingAs($customer)->get("/{$locale}/account/bookings/{$booking->public_id}")
        ->assertOk()
        ->assertSee(__('account.refund_guidance.in_progress', [], $locale))
        ->assertSee(__('refunds.status.pending', [], $locale))
        ->assertSee(Money::ofMinor(90000, 'EGP')->formatTo($moneyLocale))
        ->assertSee(Money::ofMinor(0, 'EGP')->formatTo($moneyLocale));
})->with(['en', 'ar']);

it('shows a full refund and a next step when a captured payment has no refund request', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $completedBooking = Booking::factory()->cancelled()->create([
        'customer_id' => $customer->id,
        'total_minor' => 90000,
    ]);
    $completedPayment = Payment::factory()->refunded()->create([
        'booking_id' => $completedBooking->id,
        'user_id' => $customer->id,
        'amount_minor' => 90000,
    ]);
    Refund::factory()->completed()->create([
        'booking_id' => $completedBooking->id,
        'payment_id' => $completedPayment->id,
        'initiated_by' => $customer->id,
        'amount_minor' => 90000,
    ]);

    $noRefundBooking = Booking::factory()->cancelled()->create([
        'customer_id' => $customer->id,
        'total_minor' => 90000,
    ]);
    Payment::factory()->captured()->create([
        'booking_id' => $noRefundBooking->id,
        'user_id' => $customer->id,
        'amount_minor' => 90000,
    ]);

    $moneyLocale = $locale === 'ar' ? 'ar_EG' : 'en_GB';
    $this->actingAs($customer)->get("/{$locale}/account/bookings/{$completedBooking->public_id}")
        ->assertOk()
        ->assertSee(__('account.refund_guidance.full_completed', [], $locale))
        ->assertSee(__('refunds.status.completed', [], $locale))
        ->assertSee(Money::ofMinor(90000, 'EGP')->formatTo($moneyLocale));
    $this->get("/{$locale}/account/bookings/{$noRefundBooking->public_id}")
        ->assertOk()
        ->assertSee(__('account.refund_guidance.not_requested', [], $locale));
})->with(['en', 'ar']);

it('shows the remaining paid amount after a partial refund', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->cancelled()->create([
        'customer_id' => $customer->id,
        'total_minor' => 90000,
    ]);
    $payment = Payment::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => $customer->id,
        'amount_minor' => 90000,
        'status' => PartiallyRefundedState::class,
        'captured_at' => now(),
    ]);
    Refund::factory()->completed()->create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'initiated_by' => $customer->id,
        'amount_minor' => 30000,
    ]);

    $moneyLocale = $locale === 'ar' ? 'ar_EG' : 'en_GB';
    $this->actingAs($customer)->get("/{$locale}/account/bookings/{$booking->public_id}")
        ->assertOk()
        ->assertSee(__('account.refund_guidance.partial_completed', [], $locale))
        ->assertSee(__('account.net_paid_after_refunds', [], $locale))
        ->assertSee(__('refunds.status.completed', [], $locale))
        ->assertSee(Money::ofMinor(30000, 'EGP')->formatTo($moneyLocale))
        ->assertSee(Money::ofMinor(60000, 'EGP')->formatTo($moneyLocale));
})->with(['en', 'ar']);

it('uses the latest refund attempt when an older attempt failed', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->cancelled()->create([
        'customer_id' => $customer->id,
        'total_minor' => 90000,
    ]);
    $payment = Payment::factory()->refunded()->create([
        'booking_id' => $booking->id,
        'user_id' => $customer->id,
        'amount_minor' => 90000,
    ]);
    Refund::factory()->failed()->create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'initiated_by' => $customer->id,
        'amount_minor' => 90000,
        'created_at' => now()->subMinute(),
    ]);
    Refund::factory()->completed()->create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'initiated_by' => $customer->id,
        'amount_minor' => 90000,
        'created_at' => now(),
    ]);

    $this->actingAs($customer)->get("/{$locale}/account/bookings/{$booking->public_id}")
        ->assertOk()
        ->assertSee(__('account.refund_guidance.full_completed', [], $locale))
        ->assertDontSee(__('account.refund_guidance.failed', [], $locale));
})->with(['en', 'ar']);

it('uses only a customer cancellation reason when present and keeps cancelled details private to its owner', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->cancelled()->create(['customer_id' => $customer->id]);
    $reason = 'The event date changed';
    StateTransition::create([
        'transitionable_type' => Booking::class,
        'transitionable_id' => $booking->id,
        'from_state' => 'vendor_review',
        'to_state' => LifecycleStatus::Cancelled->value,
        'trigger_kind' => 'customer',
        'triggered_by' => $customer->id,
        'context' => ['reason' => $reason],
    ]);

    $this->actingAs($customer)->get("/{$locale}/account/bookings/{$booking->public_id}")
        ->assertOk()
        ->assertSee($reason);
    $this->actingAs(User::factory()->asCustomer()->create())
        ->get("/{$locale}/account/bookings/{$booking->public_id}")
        ->assertNotFound();
})->with(['en', 'ar']);

it('does not show an internal note as the customer cancellation reason when no public reason exists', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $admin = User::factory()->asAdmin()->create();
    $booking = Booking::factory()->cancelled()->create(['customer_id' => $customer->id]);
    $note = BookingAdminIntervention::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $booking->id,
        'admin_id' => $admin->id,
        'intervention_type' => InterventionType::AdminNote,
        'reason' => 'Private internal note',
        'before_state' => [],
        'after_state' => [],
    ]);
    StateTransition::create([
        'transitionable_type' => Booking::class,
        'transitionable_id' => $booking->id,
        'from_state' => 'vendor_review',
        'to_state' => LifecycleStatus::Cancelled->value,
        'trigger_kind' => 'admin',
        'triggered_by' => $admin->id,
        'context' => ['intervention_id' => $note->id, 'reason' => 'Private internal note'],
    ]);

    $this->actingAs($customer)->get("/{$locale}/account/bookings/{$booking->public_id}")
        ->assertOk()
        ->assertSee(__('account.cancellation_reason_help', [], $locale))
        ->assertDontSee('Private internal note');
})->with(['en', 'ar']);

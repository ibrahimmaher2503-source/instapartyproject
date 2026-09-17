<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\GetNegotiationMonitorQueryAction;
use App\Modules\Booking\Application\Actions\SuggestAlternativeVendorsAction;
use App\Modules\Booking\Application\DTOs\SuggestedAlternativeVendorsDTO;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource;
use App\Modules\Booking\Filament\Widgets\LateVendorResponsesWidget;
use App\Modules\Discovery\Domain\Contracts\AlternativeVendorFinder;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Models\StateTransition;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function monitorBooking(string $state): Booking
{
    return Booking::factory()->create(['lifecycle_status' => $state]);
}

function monitorVendor(Booking $booking, string $status, ?CarbonImmutable $deadline = null): BookingVendor
{
    return BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => $status,
        'response_deadline' => $deadline,
    ]);
}

it('counts distinct review bookings for late and open negotiation scopes', function (): void {
    $asOf = CarbonImmutable::parse('2026-09-12 12:00:00', 'UTC');
    $late = monitorBooking(VendorReviewState::class);
    monitorVendor($late, VendorSubStatus::Pending->value, $asOf->subMinute());
    monitorVendor($late, VendorSubStatus::Pending->value, $asOf->subMinute());

    $future = monitorBooking(VendorReviewState::class);
    monitorVendor($future, VendorSubStatus::Pending->value, $asOf->addHour());

    $modified = monitorBooking(CustomerReviewState::class);
    monitorVendor($modified, VendorSubStatus::Modified->value);

    $timedOut = monitorBooking(VendorReviewState::class);
    monitorVendor($timedOut, VendorSubStatus::TimedOut->value, $asOf->subHour());

    $completed = monitorBooking(LifecycleStatus::Completed->value);
    monitorVendor($completed, VendorSubStatus::Pending->value, $asOf->subHour());

    $action = app(GetNegotiationMonitorQueryAction::class);

    expect($action->execute('late', $asOf)->pluck('id')->all())->toBe([$late->id])
        ->and($action->execute('open', $asOf)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$late->id, $future->id, $modified->id])->sort()->values()->all());
});

it('uses the policy permission for the late responses widget', function (): void {
    $legacy = User::factory()->create();
    $legacy->givePermissionTo(Permission::findOrCreate('view_any_bookings_monitor', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($legacy);
    expect(LateVendorResponsesWidget::canView())->toBeFalse();

    $authorized = User::factory()->create();
    $authorized->givePermissionTo(Permission::findOrCreate('view_any_bookings::monitor', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($authorized);
    expect(LateVendorResponsesWidget::canView())->toBeTrue();
});

it('renders the booking intervention detail page for an authorized admin', function (): void {
    $booking = monitorBooking(VendorReviewState::class);
    monitorVendor($booking, VendorSubStatus::Pending->value, CarbonImmutable::now('UTC')->subMinute());
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('booking.intervene.access', 'web'),
        Permission::findOrCreate('view_bookings::monitor', 'web'),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get(AdminBookingInterventionResource::getUrl('view', ['record' => $booking]))->assertOk();
});

it('rejects no-op state transitions and assigns a correlation id', function (): void {
    expect(fn () => StateTransition::create([
        'transitionable_type' => Booking::class,
        'transitionable_id' => Booking::factory()->create()->id,
        'from_state' => LifecycleStatus::Cancelled->value,
        'to_state' => LifecycleStatus::Cancelled->value,
        'trigger_kind' => 'system',
    ]))->toThrow(LogicException::class);

    $booking = Booking::factory()->create();
    $transition = StateTransition::create([
        'transitionable_type' => Booking::class,
        'transitionable_id' => $booking->id,
        'from_state' => null,
        'to_state' => LifecycleStatus::Draft->value,
        'trigger_kind' => 'system',
    ]);

    expect(Str::isUuid($transition->trace_id))->toBeTrue();
});

it('does not duplicate a confirmed alternative suggestion', function (): void {
    $booking = monitorBooking(VendorReviewState::class);
    $admin = User::factory()->create();
    $finder = Mockery::mock(AlternativeVendorFinder::class);
    $finder->shouldReceive('validateCandidates')->twice();
    app()->instance(AlternativeVendorFinder::class, $finder);

    $dto = new SuggestedAlternativeVendorsDTO(
        bookingId: $booking->id,
        adminId: $admin->id,
        vendorProfileIds: [123],
        reason: 'Offer another vendor to the customer.',
        idempotencyKey: 'suggest-alternatives-once',
    );
    $action = app(SuggestAlternativeVendorsAction::class);

    $first = $action->execute($booking, $dto);
    $second = $action->execute($booking, $dto);

    expect($second->id)->toBe($first->id)
        ->and(BookingAdminIntervention::query()
            ->where('booking_id', $booking->id)
            ->where('intervention_type', 'vendor_proposal')
            ->count())->toBe(1);
});

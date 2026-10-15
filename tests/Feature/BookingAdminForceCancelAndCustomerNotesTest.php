<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\ForceCancelBookingAction;
use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingCustomerNote;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource\Pages\ListBookingsMonitor;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Models\StateTransition;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('hides force cancel for terminal bookings and displays escaped customer notes to admins', function (string $locale, string $notesLabel): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    app()->setLocale($locale);
    $this->actingAs(User::factory()->superAdmin()->create());

    $eligibleBooking = Booking::factory()->submitted()->create();
    Livewire::test(ViewBooking::class, ['record' => $eligibleBooking->public_id])
        ->assertSuccessful()
        ->assertActionVisible('forceCancel');

    $cancelledBooking = Booking::factory()->cancelled()->create();
    Livewire::test(ViewBooking::class, ['record' => $cancelledBooking->public_id])
        ->assertSuccessful()
        ->assertActionHidden('forceCancel');

    $completedBooking = Booking::factory()->completed()->create();
    Livewire::test(ViewBooking::class, ['record' => $completedBooking->public_id])
        ->assertSuccessful()
        ->assertActionHidden('forceCancel');

    $booking = Booking::factory()->create();
    $note = 'يرجى الحذر <img src=x onerror=alert(1)>';
    BookingCustomerNote::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => $booking->customer_id,
        'body' => $note,
        'detected_locale' => $locale,
    ]);

    Livewire::test(ViewBooking::class, ['record' => $booking->public_id])
        ->assertSuccessful()
        ->assertSee($notesLabel)
        ->assertSee($note)
        ->assertDontSee($note, false);
})->with([
    'English' => ['en', 'Customer Notes'],
    'Arabic' => ['ar', 'ملاحظات العميل'],
]);

it('reports a mounted stale force cancel without adding duplicate audit effects', function (string $locale): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    app()->setLocale($locale);
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $booking = Booking::factory()->submitted()->create();
    $component = Livewire::test(ViewBooking::class, ['record' => $booking->public_id])
        ->assertActionVisible('forceCancel')
        ->mountAction('forceCancel')
        ->assertSet('mountedActions', ['forceCancel'])
        ->setActionData(['reason' => 'Prevent duplicate cancellation effects.']);
    $dto = new AdminInterventionDTO(
        bookingId: $booking->id,
        adminId: $admin->id,
        interventionType: InterventionType::ForceCancel,
        reason: 'Prevent duplicate cancellation effects.',
    );
    $action = app(ForceCancelBookingAction::class);

    $vendor = BookingVendor::factory()->create(['booking_id' => $booking->id]);
    $item = BookingItem::factory()->rental()->create(['booking_vendor_id' => $vendor->id]);
    $reservation = ServiceInventoryReservation::factory()->create([
        'service_id' => $item->service_id,
        'booking_item_id' => $item->id,
        'status' => ReservationStatus::Held,
    ]);
    $unrelatedReservation = ServiceInventoryReservation::factory()->create(['status' => ReservationStatus::Held]);

    $action->execute($booking, $dto);
    expect($reservation->fresh()->status)->toBe(ReservationStatus::Released)
        ->and($unrelatedReservation->fresh()->status)->toBe(ReservationStatus::Held)
        ->and($booking->snapshots()->where('trigger_kind', 'booking_cancelled')->count())->toBe(1);
    $interventionCount = BookingAdminIntervention::query()
        ->where('booking_id', $booking->id)
        ->where('intervention_type', InterventionType::ForceCancel->value)
        ->count();
    $transitionCount = StateTransition::query()
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->count();
    $auditCount = DB::table('audit_logs')
        ->where('auditable_type', Booking::class)
        ->where('auditable_id', $booking->id)
        ->where('action', 'force_cancel_booking')
        ->count();

    $component->call('callMountedAction')
        ->assertNotified(Notification::make()
            ->title(__('booking::booking.cancellation.blocked.already_cancelled'))
            ->body(__('shared::admin-feedback.force_cancel_failed', ['reference' => $booking->public_id]))
            ->danger());

    expect(BookingAdminIntervention::query()
        ->where('booking_id', $booking->id)
        ->where('intervention_type', InterventionType::ForceCancel->value)
        ->count())->toBe($interventionCount)
        ->and(StateTransition::query()
            ->where('transitionable_type', Booking::class)
            ->where('transitionable_id', $booking->id)
            ->count())->toBe($transitionCount)
        ->and(DB::table('audit_logs')
            ->where('auditable_type', Booking::class)
            ->where('auditable_id', $booking->id)
            ->where('action', 'force_cancel_booking')
            ->count())->toBe($auditCount);
})->with(['en', 'ar']);

it('rechecks admin access before processing a mounted cancellation', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $booking = Booking::factory()->submitted()->create();
    $component = Livewire::test(ViewBooking::class, ['record' => $booking->public_id])
        ->mountAction('forceCancel')
        ->assertSet('mountedActions', ['forceCancel'])
        ->setActionData(['reason' => 'Cancellation opened before admin access was revoked.']);
    $admin->syncRoles([]);

    $component->call('callMountedAction')->assertForbidden();
    expect($booking->fresh()->lifecycle_status->getValue())->toBe(LifecycleStatus::Submitted->value)
        ->and(BookingAdminIntervention::query()->where('booking_id', $booking->id)->count())->toBe(0);
});

it('rejects a forged monitor force cancel from a monitor viewer without cancellation permission', function (): void {
    Role::findOrCreate('panel_user', 'web');
    $viewer = User::factory()->create();
    $viewer->assignRole('panel_user');
    $viewer->givePermissionTo(Permission::findOrCreate('view_any_bookings::monitor', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($viewer->can('force_cancel_booking'))->toBeFalse();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($viewer);

    $booking = Booking::factory()->create([
        'lifecycle_status' => LifecycleStatus::VendorReview->value,
    ]);
    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Pending->value,
        'response_deadline' => now('UTC')->subMinute(),
    ]);

    $component = Livewire::test(ListBookingsMonitor::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$booking])
        ->assertTableActionHidden('forceCancel', $booking)
        ->call('mountTableAction', 'forceCancel', (string) $booking->getKey())
        ->assertSet('mountedTableActions', [])
        ->set('mountedTableActionsData.0.reason', 'Unauthorized cancellation attempt.');

    $component->call('callMountedTableAction')->assertSuccessful();

    expect($booking->fresh()->lifecycle_status)->toBeInstanceOf(VendorReviewState::class)
        ->and(BookingAdminIntervention::query()
            ->where('booking_id', $booking->id)
            ->where('intervention_type', InterventionType::ForceCancel->value)
            ->count())->toBe(0)
        ->and(DB::table('audit_logs')
            ->where('auditable_type', Booking::class)
            ->where('auditable_id', $booking->id)
            ->where('action', 'force_cancel_booking')
            ->count())->toBe(0);
});

it('closes a cancellation modal when the booking completes before confirmation', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->superAdmin()->create());
    $booking = Booking::factory()->submitted()->create();
    $component = Livewire::test(ViewBooking::class, ['record' => $booking->public_id])
        ->mountAction('forceCancel')
        ->assertSet('mountedActions', ['forceCancel'])
        ->setActionData(['reason' => 'Cancellation requested before the booking completed.']);
    $booking->forceFill(['lifecycle_status' => LifecycleStatus::Completed->value])->saveQuietly();

    $component->call('callMountedAction')
        ->assertSet('mountedActions', [])
        ->assertNotified(Notification::make()
            ->title(__('booking::booking.cancellation.blocked.booking_completed'))
            ->body(__('shared::admin-feedback.force_cancel_failed', ['reference' => $booking->public_id]))
            ->danger());
    expect($booking->fresh()->lifecycle_status->getValue())->toBe(LifecycleStatus::Completed->value)
        ->and(BookingAdminIntervention::query()->where('booking_id', $booking->id)->count())->toBe(0);
})->with(['en', 'ar']);

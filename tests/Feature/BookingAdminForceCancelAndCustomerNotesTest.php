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
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource\Pages\ListBookingsMonitor;
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
    Livewire::test(ViewBooking::class, ['record' => $eligibleBooking->id])
        ->assertSuccessful()
        ->assertActionVisible('forceCancel');

    $cancelledBooking = Booking::factory()->cancelled()->create();
    Livewire::test(ViewBooking::class, ['record' => $cancelledBooking->id])
        ->assertSuccessful()
        ->assertActionHidden('forceCancel');

    $completedBooking = Booking::factory()->completed()->create();
    Livewire::test(ViewBooking::class, ['record' => $completedBooking->id])
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

    Livewire::test(ViewBooking::class, ['record' => $booking->id])
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
    $component = Livewire::test(ViewBooking::class, ['record' => $booking->id])
        ->assertActionVisible('forceCancel')
        ->mountAction('forceCancel')
        ->fillForm(['reason' => 'Prevent duplicate cancellation effects.']);
    $dto = new AdminInterventionDTO(
        bookingId: $booking->id,
        adminId: $admin->id,
        interventionType: InterventionType::ForceCancel,
        reason: 'Prevent duplicate cancellation effects.',
    );
    $action = app(ForceCancelBookingAction::class);

    $action->execute($booking, $dto);
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

    $component->callMountedAction()
        ->assertNotified(Notification::make()
            ->title(__('booking::booking.cancellation.blocked.already_cancelled'))
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
        ->call('mountTableAction', 'forceCancel', (string) $booking->getKey())
        ->set('mountedTableActionsData.0.reason', 'Unauthorized cancellation attempt.');

    $component->call('callMountedTableAction')->assertForbidden();

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

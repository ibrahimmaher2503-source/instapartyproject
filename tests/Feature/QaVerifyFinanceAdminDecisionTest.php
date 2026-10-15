<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AdminDecidePendingBookingVendorAction;
use App\Modules\Booking\Application\Actions\BuildAlternativeVendorOfferAction;
use App\Modules\Booking\Application\Actions\SuggestAlternativeVendorsAction;
use App\Modules\Booking\Application\DTOs\SuggestedAlternativeVendorsDTO;
use App\Modules\Booking\Database\Factories\BookingFactory;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Enums\AdminVendorDecision;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Communication\Application\Actions\AcknowledgeInboxItemAction;
use App\Modules\Communication\Application\Listeners\OnBookingSubmittedInbox;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Domain\Models\AdminInboxRoutingRule;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Models\StateTransition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

function financeDecisionAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('booking.intervene.decide_vendor_request', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $admin;
}

it('records an authorized admin vendor acceptance and confirms the booking once all vendors accept', function (): void {
    $booking = BookingFactory::new()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);
    $vendor = BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Pending,
        'response_deadline' => CarbonImmutable::now('UTC')->addHour(),
    ]);
    $admin = financeDecisionAdmin();
    $this->actingAs($admin, 'web');

    $intervention = app(AdminDecidePendingBookingVendorAction::class)->execute(
        $vendor,
        AdminVendorDecision::Accept,
        'Vendor confirmed stock and schedule with the customer request.',
    );

    $vendor->refresh();
    $booking->refresh();
    expect($vendor->sub_status)->toBe(VendorSubStatus::Accepted)
        ->and($booking->lifecycle_status)->toBeInstanceOf(ConfirmedState::class)
        ->and($intervention->admin_id)->toBe($admin->id)
        ->and($intervention->intervention_type->value)->toBe('vendor_decision')
        ->and($intervention->before_state['sub_status'])->toBe('pending')
        ->and($intervention->after_state['sub_status'])->toBe('accepted')
        ->and(StateTransition::query()->where('transitionable_type', BookingVendor::class)->where('transitionable_id', $vendor->id)->where('trigger_kind', 'admin')->sole()->triggered_by)->toBe($admin->id)
        ->and(DB::table('audit_logs')->where('action', 'booking.vendor_decision')->where('user_id', $admin->id)->count())->toBe(1)
        ->and(BookingAdminIntervention::query()->where('booking_id', $booking->id)->count())->toBe(1);
});

it('moves a rejected vendor request to customer review without changing booking amounts', function (): void {
    $booking = BookingFactory::new()->create([
        'lifecycle_status' => VendorReviewState::class,
        'total_minor' => 45678,
        'total_currency' => 'EGP',
    ]);
    $vendor = BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Pending,
        'response_deadline' => CarbonImmutable::now('UTC')->addHour(),
    ]);
    BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Accepted,
    ]);
    $admin = financeDecisionAdmin();
    $this->actingAs($admin, 'web');

    $intervention = app(AdminDecidePendingBookingVendorAction::class)->execute(
        $vendor,
        AdminVendorDecision::Reject,
        'Vendor cannot serve this date, so the customer should review options.',
    );

    expect($vendor->fresh()->sub_status)->toBe(VendorSubStatus::Rejected)
        ->and($booking->fresh()->lifecycle_status)->toBeInstanceOf(CustomerReviewState::class)
        ->and((int) $booking->fresh()->total_minor)->toBe(45678)
        ->and($intervention->before_state['sub_status'])->toBe('pending')
        ->and($intervention->after_state['sub_status'])->toBe('rejected');
});

it('refuses an expired pending vendor decision without creating an audit record', function (): void {
    $booking = BookingFactory::new()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);
    $vendor = BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Pending,
        'response_deadline' => CarbonImmutable::now('UTC')->subSecond(),
    ]);
    $admin = financeDecisionAdmin();
    $this->actingAs($admin, 'web');

    expect(fn () => app(AdminDecidePendingBookingVendorAction::class)->execute(
        $vendor,
        AdminVendorDecision::Accept,
        'Vendor confirmed stock and schedule with the customer request.',
    ))->toThrow(ResponseDeadlineExpiredException::class)
        ->and($vendor->fresh()->sub_status)->toBe(VendorSubStatus::Pending)
        ->and(BookingAdminIntervention::query()->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('action', 'booking.vendor_decision')->count())->toBe(0);
});

it('routes one pending vendor inbox alert per booking and preserves its read state on duplicate vendor events', function (): void {
    $admin = User::factory()->asAdmin()->create();
    AdminInboxRoutingRule::factory()->forRole(Role::findByName('admin')->id)->create([
        'event_key' => 'booking.vendor_request.pending',
        'severity' => AdminInboxSeverity::Warning,
    ]);
    $booking = BookingFactory::new()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);
    $vendors = collect([1, 2])->map(fn () => BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Pending,
        'response_deadline' => CarbonImmutable::now('UTC')->addHour(),
    ]));
    $listener = app(OnBookingSubmittedInbox::class);

    foreach ($vendors as $vendor) {
        $listener->handle(new BookingSubmittedToVendor($vendor, $booking));
    }

    $item = AdminInboxItem::query()->sole();
    expect($item->admin_id)->toBe($admin->id)
        ->and($item->source_type)->toBe('booking')
        ->and($item->source_id)->toBe($booking->id)
        ->and($item->status)->toBe(AdminInboxStatus::Unread)
        ->and($item->getTranslation('title', 'en'))->toContain($booking->public_id);

    app(AcknowledgeInboxItemAction::class)->execute($item, $admin);
    $listener->handle(new BookingSubmittedToVendor($vendors->first(), $booking));

    expect(AdminInboxItem::query()->count())->toBe(1)
        ->and($item->fresh()->status)->toBe(AdminInboxStatus::Read);
});

it('keeps the assigned vendor and booking state unchanged when admins only propose alternatives', function (): void {
    $booking = BookingFactory::new()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);
    $assignedVendor = BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Pending,
        'response_deadline' => CarbonImmutable::now('UTC')->addHour(),
    ]);
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('booking.intervene.suggest_alternative_vendors', 'web'));
    $candidateId = $assignedVendor->vendor_profile_id + 100;
    $offerBuilder = Mockery::mock(BuildAlternativeVendorOfferAction::class);
    $offerBuilder->shouldReceive('execute')->once()->with(
        Mockery::on(fn (BookingVendor $vendor): bool => $vendor->is($assignedVendor)),
        $candidateId,
    )->andReturn([
        'candidate_vendor' => ['public_id' => '01JQAVQ71JH8X8SS0KJ6A4F1YQ', 'business_name' => ['en' => 'Alternative', 'ar' => 'بديل']],
        'response_deadline' => $assignedVendor->response_deadline->utc()->toIso8601String(),
        'subtotal_minor' => $assignedVendor->subtotal_minor,
        'delivery_fee_minor' => $assignedVendor->delivery_fee_minor,
        'currency' => 'EGP',
        'total_minor' => $assignedVendor->subtotal_minor + $assignedVendor->delivery_fee_minor,
        'items' => [],
        'offer_hash' => hash('sha256', 'offer'),
    ]);
    $offerBuilder->shouldReceive('sourceSnapshot')->once()->with(Mockery::on(
        fn (BookingVendor $vendor): bool => $vendor->is($assignedVendor),
    ))->andReturn([
        'booking_vendor_public_id' => $assignedVendor->public_id,
        'vendor_profile_id' => $assignedVendor->vendor_profile_id,
    ]);
    app()->instance(BuildAlternativeVendorOfferAction::class, $offerBuilder);

    $intervention = app(SuggestAlternativeVendorsAction::class)->execute($booking, new SuggestedAlternativeVendorsDTO(
        bookingId: $booking->id,
        adminId: $admin->id,
        bookingVendorPublicId: $assignedVendor->public_id,
        vendorProfileId: $candidateId,
        reason: 'Offer an eligible alternative for the customer to review.',
        idempotencyKey: null,
    ));

    expect($assignedVendor->fresh()->vendor_profile_id)->toBe($assignedVendor->vendor_profile_id)
        ->and($assignedVendor->fresh()->sub_status)->toBe(VendorSubStatus::Pending)
        ->and($booking->fresh()->lifecycle_status)->toBeInstanceOf(VendorReviewState::class)
        ->and($intervention->admin_id)->toBe($admin->id)
        ->and($intervention->proposed_vendor_id)->toBeNull()
        ->and($intervention->after_state['offer']['candidate_vendor']['public_id'])->toBe('01JQAVQ71JH8X8SS0KJ6A4F1YQ');
});

it('rejects alternative vendor suggestions from an actor without the admin permission', function (): void {
    $booking = BookingFactory::new()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);
    $admin = User::factory()->create();
    expect(fn () => app(SuggestAlternativeVendorsAction::class)->execute($booking, new SuggestedAlternativeVendorsDTO(
        bookingId: $booking->id,
        adminId: $admin->id,
        bookingVendorPublicId: '01JQAVQ71JH8X8SS0KJ6A4F1YQ',
        vendorProfileId: 123,
        reason: 'Please review alternative vendor choices.',
    )))->toThrow(HttpException::class);
});

it('requires a meaningful reason before an admin may suggest alternatives', function (): void {
    $booking = BookingFactory::new()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('booking.intervene.suggest_alternative_vendors', 'web'));
    expect(fn () => app(SuggestAlternativeVendorsAction::class)->execute($booking, new SuggestedAlternativeVendorsDTO(
        bookingId: $booking->id,
        adminId: $admin->id,
        bookingVendorPublicId: '01JQAVQ71JH8X8SS0KJ6A4F1YQ',
        vendorProfileId: 123,
        reason: ' ',
    )))->toThrow(ValidationException::class)
        ->and(BookingAdminIntervention::query()->count())->toBe(0);
});

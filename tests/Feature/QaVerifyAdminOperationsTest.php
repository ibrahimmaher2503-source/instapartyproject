<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource\Pages\ListBookingsMonitor;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages\ListVendorApprovalQueue;
use App\Modules\Shared\Domain\Models\StateTransition;
use App\Modules\Shared\Filament\Widgets\AdminDashboardSectionsWidget;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function qaVerifyAdminOperationsActor(array $permissions): User
{
    $actor = User::factory()->asAdmin()->create();

    foreach ($permissions as $permission) {
        $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $actor->unsetRelation('permissions');

    return $actor;
}

function qaVerifyEligibleVendorProfile(bool $withBusinessHours = true): VendorProfile
{
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $user = User::factory()->phoneVerified()->create([
        'name' => 'QA Operations Vendor',
        'email_verified_at' => now(),
        'phone_verified_at' => now(),
    ]);
    $profile = VendorProfile::factory()->pending()->create([
        'user_id' => $user->id,
        'business_name' => ['en' => 'QA Operations Events', 'ar' => 'فعاليات اختبار العمليات'],
        'address_line' => ['en' => 'QA address', 'ar' => 'عنوان اختبار'],
        'business_type' => 'individual',
        'national_id' => '29901011234567',
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
        'bank_name' => 'QA Bank',
        'bank_account_holder' => 'QA Operations Vendor',
        'bank_iban' => 'EG380019000500000000263180002',
    ]);

    if ($withBusinessHours) {
        VendorBusinessHour::factory()->create(['vendor_profile_id' => $profile->id]);
    }

    VendorCoverageArea::factory()->create([
        'vendor_profile_id' => $profile->id,
        'city_id' => $city->id,
    ]);

    foreach ([DocumentType::NationalId, DocumentType::IbanProof] as $type) {
        VendorDocument::factory()->approved()->create([
            'vendor_profile_id' => $profile->id,
            'doc_type' => $type,
            'expires_at' => today(),
            'is_critical' => true,
        ]);
    }

    return $profile;
}

it('approves eligible selected vendors and reports ineligible selections individually', function (string $locale): void {
    Event::fake();
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(qaVerifyAdminOperationsActor([
        'view_any_vendor::profile',
        'view_vendor::profile',
        'approve_vendor_profile',
    ]));

    $eligible = qaVerifyEligibleVendorProfile();
    $ineligible = qaVerifyEligibleVendorProfile(withBusinessHours: false);
    $eligibilityErrors = app(VendorApprovalEligibilityService::class)
        ->evaluate($ineligible)
        ->errors;
    $reason = collect($eligibilityErrors)->flatten()->implode(' ');
    $body = implode("\n", [
        __('identity.notifications.vendor_bulk_approval_summary', ['approved' => 1, 'failed' => 1]),
        __('identity.notifications.vendor_bulk_approval_approved', ['id' => $eligible->public_id]),
        __('identity.notifications.vendor_bulk_approval_ineligible', ['id' => $ineligible->public_id, 'reason' => $reason]),
    ]);

    Livewire::test(ListVendorApprovalQueue::class)
        ->assertTableBulkActionVisible('approve_selected')
        ->callTableBulkAction('approve_selected', [$eligible, $ineligible])
        ->assertNotified(Notification::make()
            ->title(__('identity.notifications.vendor_bulk_approval_result'))
            ->body($body)
            ->warning());

    expect($eligible->refresh()->approval_status)->toBeInstanceOf(ApprovedState::class)
        ->and($ineligible->refresh()->approval_status)->toBeInstanceOf(PendingState::class);
})->with(['en', 'ar']);

it('hides selected-vendor approval from admins without approval permission', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(qaVerifyAdminOperationsActor(['view_any_vendor::profile', 'view_vendor::profile']));

    Livewire::test(ListVendorApprovalQueue::class)
        ->assertTableBulkActionHidden('approve_selected');
});

it('exports filtered monitor bookings using only public identifiers and operational fields', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(qaVerifyAdminOperationsActor(['view_any_bookings::monitor']));

    $included = Booking::factory()->create([
        'lifecycle_status' => LifecycleStatus::VendorReview->value,
        'submitted_at' => now(),
        'total_minor' => 45000,
        'total_currency' => 'EGP',
    ]);
    $excluded = Booking::factory()->create([
        'lifecycle_status' => LifecycleStatus::CustomerReview->value,
        'submitted_at' => now(),
    ]);

    $component = Livewire::test(ListBookingsMonitor::class)
        ->filterTable('lifecycle_status', LifecycleStatus::VendorReview->value)
        ->assertCanSeeTableRecords([$included])
        ->assertCanNotSeeTableRecords([$excluded])
        ->assertTableHeaderActionsExistInOrder(['export']);

    $exportAction = $component->instance()->getTable()->getHeaderActions()[0];
    $export = \Livewire\invade($exportAction)->exports->first();
    $export->hydrate(livewire: $component->instance());

    $exportColumns = array_keys($export->map($included));
    sort($exportColumns);
    $exported = $export->map($included);
    $expectedColumns = [
        'public_id',
        'reference_no',
        'lifecycle_status',
        'payment_status',
        'total_minor',
        'total_currency',
        'submitted_at',
        'event_starts_at',
        'event_ends_at',
    ];
    sort($expectedColumns);

    expect($export->getQuery()->pluck('id')->all())->toBe([$included->id])
        ->and($exportColumns)->toBe($expectedColumns)
        ->and($exported['total_minor'])->toBe((string) $included->total_minor)
        ->and($exported['total_currency'])->toBe('EGP')
        ->and($exported['lifecycle_status'])->toBe(__('booking.lifecycle_status.vendor_review'))
        ->and(str_ends_with((string) $exported['event_starts_at'], '+00:00'))->toBeTrue();
});

it('shows booking queue age and sorts the oldest submitted booking first', function (): void {
    $this->freezeTime();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(qaVerifyAdminOperationsActor(['view_any_bookings::monitor']));

    $oldest = Booking::factory()->create([
        'lifecycle_status' => LifecycleStatus::VendorReview->value,
        'submitted_at' => now('UTC')->subDays(3),
        'created_at' => now('UTC')->subDays(3),
    ]);
    $newer = Booking::factory()->create([
        'lifecycle_status' => LifecycleStatus::CustomerReview->value,
        'submitted_at' => now('UTC')->subHours(6),
        'created_at' => now('UTC')->subHours(6),
    ]);
    foreach ([$oldest, $newer] as $booking) {
        BookingVendor::factory()->create([
            'booking_id' => $booking->id,
            'sub_status' => VendorSubStatus::Pending->value,
            'response_deadline' => now('UTC')->addHours(8),
        ]);
    }

    $page = Livewire::test(ListBookingsMonitor::class)
        ->assertTableColumnExists('queue_age')
        ->assertCanSeeTableRecords([$oldest, $newer])
        ->assertSee($oldest->submitted_at->diffForHumans(now('UTC')));

    expect($page->instance()->getFilteredSortedTableQuery()->pluck('id')->all())
        ->toBe([$oldest->id, $newer->id]);
});

it('leaves conversion percentages unavailable for an empty submission cohort', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(qaVerifyAdminOperationsActor(['view_any_booking']));

    $funnel = app(AdminDashboardSectionsWidget::class)->getViewData()['operations']['booking_funnel'];

    expect($funnel['submitted_records'])->toBe(0)
        ->and($funnel['tracked_submissions'])->toBe(0)
        ->and($funnel['transition_coverage_percent'])->toBeNull()
        ->and(collect($funnel['stages'])->pluck('conversion_percent')->all())->toBe([null, null, null, null]);
});

it('builds the booking funnel from tracked submitted cohorts and reports transition coverage', function (): void {
    $this->freezeTime();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(qaVerifyAdminOperationsActor(['view_any_booking']));

    $tracked = Booking::factory()->create(['submitted_at' => now('UTC')->subDays(2)->subHour()]);
    $completedProgress = Booking::factory()->create(['submitted_at' => now('UTC')->subDays(3)]);
    $untracked = Booking::factory()->create(['submitted_at' => now('UTC')->subDays(1)]);
    $outsideWindow = Booking::factory()->create(['submitted_at' => now('UTC')->subDays(8)]);

    $recordTransition = static function (Booking $booking, string $from, string $to, CarbonImmutable $at): void {
        $transition = StateTransition::query()->create([
            'transitionable_type' => Booking::class,
            'transitionable_id' => $booking->id,
            'from_state' => $from,
            'to_state' => $to,
            'trigger_kind' => 'system',
        ]);
        $transition->forceFill(['created_at' => $at])->saveQuietly();
    };

    $recordTransition($tracked, 'draft', 'vendor_review', CarbonImmutable::now('UTC')->subDays(2));
    $recordTransition($tracked, 'vendor_review', 'confirmed', CarbonImmutable::now('UTC')->subDays(2)->subMinutes(30));
    $otherModelTransition = StateTransition::query()->create([
        'transitionable_type' => User::class,
        'transitionable_id' => $tracked->id,
        'from_state' => 'active',
        'to_state' => 'completed',
        'trigger_kind' => 'system',
    ]);
    $otherModelTransition->forceFill(['created_at' => CarbonImmutable::now('UTC')])->saveQuietly();
    $recordTransition($completedProgress, 'draft', 'vendor_review', CarbonImmutable::now('UTC')->subDays(3));
    $recordTransition($completedProgress, 'vendor_review', 'confirmed', CarbonImmutable::now('UTC')->subDay());
    $recordTransition($completedProgress, 'confirmed', 'active', CarbonImmutable::now('UTC'));
    $recordTransition($untracked, 'customer_review', 'vendor_review', CarbonImmutable::now('UTC')->subHours(12));
    $recordTransition($outsideWindow, 'draft', 'vendor_review', CarbonImmutable::now('UTC')->subDays(8));

    $funnel = app(AdminDashboardSectionsWidget::class)->getViewData()['operations']['booking_funnel'];

    expect($funnel['submitted_records'])->toBe(3)
        ->and($funnel['tracked_submissions'])->toBe(2)
        ->and($funnel['transition_coverage_percent'])->toBe(66.7)
        ->and($funnel['stages'])->toBe([
            ['key' => 'submitted', 'count' => 2, 'conversion_percent' => 100.0, 'bar_percent' => 100.0],
            ['key' => 'confirmed', 'count' => 1, 'conversion_percent' => 50.0, 'bar_percent' => 50.0],
            ['key' => 'active', 'count' => 1, 'conversion_percent' => 50.0, 'bar_percent' => 50.0],
            ['key' => 'completed', 'count' => 0, 'conversion_percent' => 0.0, 'bar_percent' => 0.0],
        ]);
});

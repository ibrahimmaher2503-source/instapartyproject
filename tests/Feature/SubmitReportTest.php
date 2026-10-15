<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Database\Factories\AdminInboxRoutingRuleFactory;
use App\Modules\Communication\Database\Factories\NotificationTemplateFactory;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\TrustSafety\Domain\Enums\ReportReason;
use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
use App\Modules\TrustSafety\Domain\Events\ReportSubmitted;
use App\Modules\TrustSafety\Domain\Models\Report;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('creates reports for vendor profiles using the authenticated customer', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    Sanctum::actingAs($customer);
    Event::fake([ReportSubmitted::class]);

    $this->postJson('/api/v1/customer/reports', [
        'reportable_type' => 'vendor_profile',
        'reportable_id' => $vendor->public_id,
        'reason' => 'other',
        'details' => 'A customer report for the vendor profile.',
    ])->assertCreated();

    expect(Report::query()
        ->where('reporter_id', $customer->id)
        ->where('reportable_type', 'vendor_profile')
        ->where('reportable_id', $vendor->id)
        ->exists())->toBeTrue();
    Event::assertDispatched(ReportSubmitted::class);
});

it('completes valid vendor reports through the configured submission listeners', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $admin = User::factory()->asAdmin()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    AdminInboxRoutingRuleFactory::new()->forAdmin($admin->id)->create([
        'event_key' => 'trust_safety.report_submitted',
        'severity' => AdminInboxSeverity::Warning->value,
    ]);

    foreach ([NotificationChannel::Email, NotificationChannel::InApp] as $channel) {
        NotificationTemplateFactory::new()->create([
            'event_key' => 'report.submitted.ack',
            'channel' => $channel,
            'audience' => NotificationAudience::Customer,
            'subject' => ['en' => 'Report received', 'ar' => 'تم استلام البلاغ'],
            'body' => ['en' => 'Report {{report_id}} received.', 'ar' => 'تم استلام البلاغ {{report_id}}.'],
            'variables' => ['report_id'],
        ]);
    }

    config(['queue.default' => 'sync']);
    Bus::fake([DispatchNotificationJob::class]);
    Sanctum::actingAs($customer);

    $this->postJson('/api/v1/customer/reports', [
        'reportable_type' => 'vendor_profile',
        'reportable_id' => $vendor->public_id,
        'reason' => 'other',
    ])->assertCreated();

    $report = Report::query()
        ->where('reporter_id', $customer->id)
        ->where('reportable_type', 'vendor_profile')
        ->where('reportable_id', $vendor->id)
        ->firstOrFail();

    expect(AdminInboxItem::query()
        ->where('admin_id', $admin->id)
        ->where('source_type', 'report')
        ->where('source_id', $report->id)
        ->exists())->toBeTrue();

    $dispatches = NotificationDispatch::query()
        ->where('user_id', $customer->id)
        ->where('reference_type', 'report')
        ->where('reference_id', $report->id)
        ->get();

    expect($dispatches
        ->map(static fn (NotificationDispatch $dispatch): string => $dispatch->channel->value)
        ->sort()
        ->values()
        ->all())->toBe(['email', 'in_app']);
    Bus::assertDispatched(DispatchNotificationJob::class, 2);
});

it('rejects unsupported chat-thread report targets without creating a report', function (): void {
    Sanctum::actingAs(User::factory()->asCustomer()->create());

    $this->postJson('/api/v1/customer/reports', [
        'reportable_type' => 'chat_thread',
        'reportable_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'reason' => 'other',
        'details' => 'The target does not exist.',
    ])->assertUnprocessable()->assertJsonValidationErrors('reportable_type');

    expect(Report::query()->count())->toBe(0);
});

it('returns not found for a missing vendor target without creating a report', function (): void {
    Sanctum::actingAs(User::factory()->asCustomer()->create());

    $this->postJson('/api/v1/customer/reports', [
        'reportable_type' => 'vendor_profile',
        'reportable_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'reason' => 'other',
    ])->assertNotFound();

    expect(Report::query()->count())->toBe(0);
});

it('returns conflict when the customer already has an open vendor report', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    Report::query()->create([
        'reporter_id' => $customer->id,
        'reportable_type' => 'vendor_profile',
        'reportable_id' => $vendor->id,
        'reason' => ReportReason::Other->value,
        'details' => null,
        'status' => ReportStatus::Open->value,
    ]);
    Sanctum::actingAs($customer);

    $this->postJson('/api/v1/customer/reports', [
        'reportable_type' => 'vendor_profile',
        'reportable_id' => $vendor->public_id,
        'reason' => 'other',
    ])->assertStatus(409);

    expect(Report::query()->count())->toBe(1);
});

it('keeps report submission restricted to customer accounts', function (): void {
    $vendorUser = User::factory()->asVendor()->create();
    $target = VendorProfile::factory()->approved()->create();
    Sanctum::actingAs($vendorUser);

    $this->postJson('/api/v1/customer/reports', [
        'reportable_type' => 'vendor_profile',
        'reportable_id' => $target->public_id,
        'reason' => 'other',
    ])->assertForbidden();

    expect(Report::query()->count())->toBe(0);
});

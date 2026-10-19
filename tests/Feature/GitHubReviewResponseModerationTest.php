<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Application\Actions\ModerateReviewResponseAction;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewModerationLog;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use App\Modules\Reviews\Filament\Resources\ReviewResponseResource\Pages\ListReviewResponses;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('shows the vendor submitted response in admin moderation and persists the decision', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_vendor::review', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate('update_vendor::review', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $vendor = VendorProfile::factory()->approved()->create();
    $review = VendorReview::factory()->create(['vendor_profile_id' => $vendor->id]);
    $response = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Vendor,
        'review_id' => $review->id,
        'vendor_profile_id' => $vendor->id,
        'body' => 'Thank you for visiting our shop.',
        'moderation_status' => ModerationStatus::Pending,
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/review-responses')->assertOk()->assertSee($response->body);

    Livewire::test(ListReviewResponses::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$response])
        ->assertTableColumnStateSet('body', $response->body, $response)
        ->callTableAction('approve', $response);

    expect($response->fresh()->moderation_status)->toBe(ModerationStatus::Approved);
    $this->assertDatabaseHas('review_moderation_log', [
        'review_type' => 'vendor_response',
        'review_id' => $response->id,
        'from_status' => 'pending',
        'to_status' => 'approved',
        'moderator_id' => $admin->id,
    ]);

    expect(ReviewModerationLog::query()
        ->where('review_type', 'vendor_response')
        ->where('review_id', $response->id)
        ->count())->toBe(1);
});

it('does not let a review viewer moderate a response without its update permission', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_vendor::review', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $vendor = VendorProfile::factory()->approved()->create();
    $review = VendorReview::factory()->create(['vendor_profile_id' => $vendor->id]);
    $response = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Vendor,
        'review_id' => $review->id,
        'vendor_profile_id' => $vendor->id,
        'moderation_status' => ModerationStatus::Pending,
    ]);
    $approvedReview = VendorReview::factory()->create(['vendor_profile_id' => $vendor->id]);
    $approvedResponse = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Vendor,
        'review_id' => $approvedReview->id,
        'vendor_profile_id' => $vendor->id,
        'moderation_status' => ModerationStatus::Approved,
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListReviewResponses::class)
        ->assertCanSeeTableRecords([$response])
        ->assertTableActionHidden('approve', $response)
        ->assertTableActionHidden('reject', $response);

    Livewire::test(ListReviewResponses::class)
        ->filterTable('moderation_status', 'approved')
        ->assertCanSeeTableRecords([$approvedResponse])
        ->assertTableActionHidden('hide', $approvedResponse);

    expect(fn () => app(ModerateReviewResponseAction::class)->execute(
        $response,
        ModerationStatus::Approved,
    ))->toThrow(AuthorizationException::class);
    expect(fn () => app(ModerateReviewResponseAction::class)->execute(
        $response,
        ModerationStatus::Rejected,
    ))->toThrow(AuthorizationException::class);
    expect(fn () => app(ModerateReviewResponseAction::class)->execute(
        $approvedResponse,
        ModerationStatus::Hidden,
    ))->toThrow(AuthorizationException::class);

    expect($response->fresh()->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($approvedResponse->fresh()->moderation_status)->toBe(ModerationStatus::Approved)
        ->and(ReviewModerationLog::query()->where('review_type', 'vendor_response')->count())->toBe(0);
});

it('limits review response access by review permission and matching vendor ownership', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_service::review', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate('update_service::review', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $vendor = VendorProfile::factory()->approved()->create();
    $service = Service::factory()->published()->create(['vendor_profile_id' => $vendor->id]);
    $review = ServiceReview::factory()->create(['service_id' => $service->id]);
    $ownedResponse = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Service,
        'review_id' => $review->id,
        'vendor_profile_id' => $vendor->id,
        'body' => 'A service response from its owner.',
    ]);
    $foreignVendor = VendorProfile::factory()->approved()->create();
    $foreignResponse = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Service,
        'review_id' => $review->id,
        'vendor_profile_id' => $foreignVendor->id,
        'body' => 'A mismatched service response.',
    ]);
    $otherTypeReview = VendorReview::factory()->create(['vendor_profile_id' => $vendor->id]);
    $otherTypeResponse = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Vendor,
        'review_id' => $otherTypeReview->id,
        'vendor_profile_id' => $vendor->id,
        'body' => 'A vendor-review response without its moderation permission.',
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListReviewResponses::class)
        ->assertCanSeeTableRecords([$ownedResponse])
        ->assertCanNotSeeTableRecords([$foreignResponse, $otherTypeResponse]);

    expect(fn () => app(ModerateReviewResponseAction::class)->execute(
        $foreignResponse,
        ModerationStatus::Approved,
    ))->toThrow(AuthorizationException::class);

    expect($foreignResponse->fresh()->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($otherTypeResponse->fresh()->moderation_status)->toBe(ModerationStatus::Pending);
});

it('keeps approve reject and hide transitions aligned with review moderation', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_vendor::review', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate('update_vendor::review', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $vendor = VendorProfile::factory()->approved()->create();
    $pendingReview = VendorReview::factory()->create(['vendor_profile_id' => $vendor->id]);
    $pendingResponse = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Vendor,
        'review_id' => $pendingReview->id,
        'vendor_profile_id' => $vendor->id,
        'moderation_status' => ModerationStatus::Pending,
    ]);
    $approvedReview = VendorReview::factory()->create(['vendor_profile_id' => $vendor->id]);
    $approvedResponse = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Vendor,
        'review_id' => $approvedReview->id,
        'vendor_profile_id' => $vendor->id,
        'moderation_status' => ModerationStatus::Approved,
    ]);
    $hiddenReview = VendorReview::factory()->create(['vendor_profile_id' => $vendor->id]);
    $hiddenResponse = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Vendor,
        'review_id' => $hiddenReview->id,
        'vendor_profile_id' => $vendor->id,
        'moderation_status' => ModerationStatus::Hidden,
    ]);
    $rejectedReview = VendorReview::factory()->create(['vendor_profile_id' => $vendor->id]);
    $rejectedResponse = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Vendor,
        'review_id' => $rejectedReview->id,
        'vendor_profile_id' => $vendor->id,
        'moderation_status' => ModerationStatus::Rejected,
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListReviewResponses::class)
        ->assertTableActionVisible('approve', $pendingResponse)
        ->assertTableActionVisible('reject', $pendingResponse)
        ->assertTableActionHidden('hide', $pendingResponse)
        ->callTableAction('reject', $pendingResponse, data: ['reason_en' => 'The response contains private information.']);

    Livewire::test(ListReviewResponses::class)
        ->filterTable('moderation_status', 'approved')
        ->assertTableActionVisible('reject', $approvedResponse)
        ->assertTableActionVisible('hide', $approvedResponse)
        ->assertTableActionHidden('approve', $approvedResponse)
        ->callTableAction('hide', $approvedResponse);

    Livewire::test(ListReviewResponses::class)
        ->filterTable('moderation_status', 'hidden')
        ->assertTableActionVisible('approve', $hiddenResponse)
        ->assertTableActionHidden('reject', $hiddenResponse)
        ->assertTableActionHidden('hide', $hiddenResponse)
        ->callTableAction('approve', $hiddenResponse);

    Livewire::test(ListReviewResponses::class)
        ->filterTable('moderation_status', 'rejected')
        ->assertTableActionHidden('approve', $rejectedResponse)
        ->assertTableActionHidden('reject', $rejectedResponse)
        ->assertTableActionHidden('hide', $rejectedResponse);

    expect($pendingResponse->fresh()->moderation_status)->toBe(ModerationStatus::Rejected)
        ->and($approvedResponse->fresh()->moderation_status)->toBe(ModerationStatus::Hidden)
        ->and($hiddenResponse->fresh()->moderation_status)->toBe(ModerationStatus::Approved)
        ->and(ReviewModerationLog::query()->where('review_type', 'vendor_response')->count())->toBe(3);
});

it('uses the persisted response status when a moderator submits a stale record', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_vendor::review', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate('update_vendor::review', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $vendor = VendorProfile::factory()->approved()->create();
    $review = VendorReview::factory()->create(['vendor_profile_id' => $vendor->id]);
    $response = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Vendor,
        'review_id' => $review->id,
        'vendor_profile_id' => $vendor->id,
        'moderation_status' => ModerationStatus::Pending,
    ]);
    $staleResponse = $response;
    $response->update(['moderation_status' => ModerationStatus::Approved]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect(fn () => app(ModerateReviewResponseAction::class)->execute(
        $staleResponse,
        ModerationStatus::Approved,
    ))->toThrow(InvalidArgumentException::class);

    expect($response->fresh()->moderation_status)->toBe(ModerationStatus::Approved)
        ->and(ReviewModerationLog::query()->where('review_type', 'vendor_response')->count())->toBe(0);
});

it('requires an admin panel role even when a vendor has a review permission', function (): void {
    $vendorUser = User::factory()->asVendor()->create();
    $vendorUser->givePermissionTo(Permission::findOrCreate('view_any_vendor::review', 'web'));
    $vendorUser->givePermissionTo(Permission::findOrCreate('update_vendor::review', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
    $review = VendorReview::factory()->create(['vendor_profile_id' => $vendor->id]);
    $response = ReviewResponse::factory()->create([
        'review_type' => ReviewType::Vendor,
        'review_id' => $review->id,
        'vendor_profile_id' => $vendor->id,
    ]);

    $this->actingAs($vendorUser);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect(fn () => app(ModerateReviewResponseAction::class)->execute(
        $response,
        ModerationStatus::Approved,
    ))->toThrow(AuthorizationException::class);

    expect($response->fresh()->moderation_status)->toBe(ModerationStatus::Pending);
});

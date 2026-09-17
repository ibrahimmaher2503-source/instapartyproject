<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\ApproveVendorProfileAction;
use App\Modules\Identity\Application\Actions\RequestVendorChangesAction;
use App\Modules\Identity\Application\Actions\VendorResubmitAfterChangesAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ChangesRequestedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

it('requires changes requested resubmission before approval and preserves the review record', function (): void {
    Event::fake();
    $managePermission = Permission::findOrCreate('manage_vendor_profile', 'web');
    $approvePermission = Permission::findOrCreate('approve_vendor_profile', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo([$managePermission, $approvePermission]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->unsetRelation('permissions');
    $vendorUser = User::factory()->create();
    $profile = VendorProfile::factory()->pending()->create(['user_id' => $vendorUser->id]);

    $request = app(RequestVendorChangesAction::class)->execute($profile, [[
        'field_path' => 'business_name.ar',
        'requested_change_en' => 'Correct the Arabic business name',
        'requested_change_ar' => 'Correct the Arabic business name AR',
    ]], $admin, 'qa-change-request-1');

    expect($profile->refresh()->approval_status)->toBeInstanceOf(ChangesRequestedState::class)
        ->and($request->status)->toBe(ChangeRequestStatus::Open)
        ->and(fn () => app(ApproveVendorProfileAction::class)->execute($profile, $admin))
        ->toThrow(UnprocessableEntityHttpException::class);

    $item = $request->items()->firstOrFail();
    $this->actingAs($vendorUser);

    $resubmitted = app(VendorResubmitAfterChangesAction::class)->execute(
        $profile,
        [$item->public_id],
        [],
        'QA correction submitted',
        'qa-resubmit-1',
    );
    $same = app(VendorResubmitAfterChangesAction::class)->execute(
        $profile,
        [$item->public_id],
        [],
        'QA correction submitted',
        'qa-resubmit-1',
    );

    expect($profile->refresh()->approval_status)->toBeInstanceOf(PendingState::class)
        ->and($resubmitted->status)->toBe(ChangeRequestStatus::Resubmitted)
        ->and($same->id)->toBe($resubmitted->id)
        ->and($resubmitted->items()->firstOrFail()->item_status->value)->toBe('addressed');
});

it('denies every lifecycle URL to an unauthenticated caller', function (): void {
    $profile = VendorProfile::factory()->create();

    foreach ([
        "/api/v1/admin/vendor-profiles/{$profile->public_id}/approve",
        "/api/v1/admin/vendor-profiles/{$profile->public_id}/reject",
        "/api/v1/admin/vendor-profiles/{$profile->public_id}/approve-for-type",
        "/api/v1/admin/vendor-profiles/{$profile->public_id}/revoke-type",
        "/api/v1/admin/vendor-profiles/{$profile->public_id}/suspend",
        "/api/v1/admin/vendor-profiles/{$profile->public_id}/reactivate",
        "/api/v1/admin/vendor-profiles/{$profile->public_id}/change-requests",
    ] as $url) {
        $this->postJson($url)->assertUnauthorized();
    }

    $document = $profile->documents()->create([
        'doc_type' => 'national_id',
        'file_path' => 'qa/nonexistent.pdf',
        'file_name' => 'nonexistent.pdf',
        'status' => 'pending',
    ]);

    $this->getJson("/api/v1/vendor/documents/{$document->public_id}/signed-url")
        ->assertUnauthorized();
});

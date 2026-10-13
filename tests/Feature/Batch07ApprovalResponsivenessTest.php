<?php

declare(strict_types=1);

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
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages\ListVendorApprovalQueue;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function batch07ApprovalActor(): User
{
    $actor = User::factory()->create();
    foreach (['view_any_vendor::profile', 'view_vendor::profile', 'approve_vendor_profile'] as $permission) {
        $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $actor->unsetRelation('permissions');

    return $actor;
}

function batch07EligibleVendorProfile(): VendorProfile
{
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $user = User::factory()->phoneVerified()->create([
        'email_verified_at' => now(),
        'phone_verified_at' => now(),
    ]);
    $profile = VendorProfile::factory()->pending()->create([
        'user_id' => $user->id,
        'business_type' => 'individual',
        'national_id' => '29901011234567',
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
        'business_name' => ['en' => 'QA Vendor', 'ar' => 'مقدم خدمة تجريبي'],
        'address_line' => ['en' => 'QA address', 'ar' => 'عنوان تجريبي'],
        'bank_name' => 'QA Bank',
        'bank_account_holder' => $user->name,
        'bank_iban' => 'EG380019000500000000263180002',
    ]);

    VendorBusinessHour::factory()->create(['vendor_profile_id' => $profile->id]);
    VendorCoverageArea::factory()->create(['vendor_profile_id' => $profile->id, 'city_id' => $city->id]);
    foreach ([DocumentType::NationalId, DocumentType::IbanProof] as $type) {
        VendorDocument::factory()->approved()->create([
            'vendor_profile_id' => $profile->id,
            'doc_type' => $type,
            'expires_at' => today(),
        ]);
    }

    return $profile;
}

it('applies approval after the admin confirms the mounted queue action', function (): void {
    Event::fake();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(batch07ApprovalActor());
    $profile = batch07EligibleVendorProfile();

    Livewire::test(ListVendorApprovalQueue::class)
        ->mountTableAction('approve', $profile)
        ->callMountedTableAction()
        ->assertNotified(Notification::make()
            ->title(__('identity.notifications.vendor_approved'))
            ->success());

    expect($profile->refresh()->approval_status)->toBeInstanceOf(ApprovedState::class);
});

it('evaluates the approval checklist from eager-loaded queue data without per-row queries', function (): void {
    $records = VendorProfile::factory()->pending()->count(3)->create();
    $expected = $records->mapWithKeys(fn (VendorProfile $profile): array => [
        $profile->getKey() => app(VendorApprovalEligibilityService::class)->evaluate($profile->fresh()),
    ]);
    $queryCount = 0;
    DB::listen(static function (QueryExecuted $query) use (&$queryCount): void {
        $queryCount++;
    });

    $profiles = app(VendorApprovalEligibilityService::class)
        ->withEligibilityRelations(VendorApprovalQueueResource::getEloquentQuery())
        ->get();
    $results = $profiles->mapWithKeys(fn (VendorProfile $profile): array => [
        $profile->getKey() => app(VendorApprovalEligibilityService::class)->evaluate($profile),
    ]);

    expect($results)->toHaveCount(3)->and($queryCount)->toBeLessThanOrEqual(6);
    foreach ($results as $key => $result) {
        expect($result->checks)->toBe($expected[$key]->checks)
            ->and($result->errors)->toBe($expected[$key]->errors);
    }
});

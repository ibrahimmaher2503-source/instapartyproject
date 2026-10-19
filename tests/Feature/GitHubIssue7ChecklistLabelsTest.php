<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

it('renders neutral approval requirements beside their independent status', function (string $locale, bool $complete): void {
    app()->setLocale($locale);
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_vendor::profile', 'web'),
        Permission::findOrCreate('view_vendor::profile', 'web'),
    ]);
    $vendor = VendorProfile::factory()->pending()->create();
    $vendor->user->update(['name' => $complete ? 'QA Vendor' : '']);
    $vendor->unsetRelation('user');
    expect(app(VendorApprovalEligibilityService::class)->evaluate($vendor)->checks['identity'])->toBe($complete);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->get(VendorApprovalQueueResource::getUrl('view', ['record' => $vendor]))
        ->assertOk()
        ->assertSee(__('identity.approval_eligibility.requirements.identity'))
        ->assertDontSee(__('identity.approval_eligibility.identity'));
})->with(['en', 'ar'])->with([true, false]);

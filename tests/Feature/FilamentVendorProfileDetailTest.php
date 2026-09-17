<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('renders the admin vendor profile detail page for an authorized user', function (): void {
    $admin = User::factory()->superAdmin()->create();

    foreach (['view_any_vendor::profile', 'view_vendor::profile'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $vendor = VendorProfile::factory()->create();

    $this->actingAs($admin)
        ->get('/admin/vendor-profiles/'.$vendor->public_id)
        ->assertOk();
});

it('renders the approval checklist for an incomplete pending vendor', function (): void {
    app()->setLocale('en');

    $admin = User::factory()->superAdmin()->create();

    foreach (['view_any_vendor::profile', 'view_vendor::profile', 'approve_vendor_profile'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $vendor = VendorProfile::factory()->pending()->create();

    $this->actingAs($admin)
        ->get('/admin/vendor-approval-queue/'.$vendor->public_id)
        ->assertOk()
        ->assertSee('Approval requirements')
        ->assertSee('Incomplete');
});

it('denies the admin vendor profile detail page without permission', function (): void {
    $user = User::factory()->create();
    $vendor = VendorProfile::factory()->create();

    $this->actingAs($user)
        ->get('/admin/vendor-profiles/'.$vendor->public_id)
        ->assertForbidden();
});

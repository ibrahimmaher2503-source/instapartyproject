<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Resources\CustomerResource;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('renders the authorized customer detail page', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->asCustomer()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_user', 'web'),
        Permission::findOrCreate('view_user', 'web'),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->unsetRelation('permissions');

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get(CustomerResource::getUrl('view', ['record' => $customer]))
        ->assertOk()
        ->assertSee($customer->name)
        ->assertSee($customer->email);
});

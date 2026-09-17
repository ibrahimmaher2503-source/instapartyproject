<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Database\Factories\WithdrawalFactory;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('renders a rejected withdrawal audit detail without ledger links', function (): void {
    $admin = User::factory()->superAdmin()->create();

    foreach (['view_any_withdrawals::queue', 'view_withdrawals::queue', 'withdrawal.view_audit'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $withdrawal = WithdrawalFactory::new()->rejected()->create();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/settlement-withdrawal-audit/'.$withdrawal->public_id)
        ->assertOk()
        ->assertSee($withdrawal->public_id)
        ->assertSee('Rejected');
});

it('renders malformed payment notes as a safe placeholder', function (): void {
    $admin = User::factory()->superAdmin()->create();

    foreach (['view_any_withdrawals::queue', 'view_withdrawals::queue', 'withdrawal.view_audit'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $withdrawal = WithdrawalFactory::new()->rejected()->create([
        'admin_payment_note' => [
            'en' => ['unexpected' => 'nested value'],
            'ar' => ['unexpected' => 'قيمة متداخلة'],
        ],
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/settlement-withdrawal-audit/'.$withdrawal->public_id)
        ->assertOk()
        ->assertSee($withdrawal->public_id)
        ->assertDontSee('unexpected');
});

<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Database\Factories\WithdrawalFactory;
use App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource\Pages\ListWithdrawalsQueue;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('shows approval provenance and history in the withdrawal modal without rewriting timestamps', function (string $locale, string $timezone): void {
    $admin = User::factory()->superAdmin()->create(['timezone' => $timezone]);
    foreach (['view_any_withdrawals::queue', 'view_withdrawals::queue', 'withdrawal.view_audit'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin);

    $withdrawal = WithdrawalFactory::new()->approved()->create([
        'requested_at' => '2026-10-12 18:50:00',
        'approved_at' => '2026-10-12 16:50:00',
        'approved_by_admin_id' => $admin->id,
    ]);
    $before = $withdrawal->refresh()->getRawOriginal();
    ksort($before);
    $actionLabels = __('audit_timeline.action_keys');

    Livewire::test(ListWithdrawalsQueue::class)
        ->call('mountTableAction', 'view', (string) $withdrawal->id)
        ->assertSeeText(__('settlement.fields.approved_at'))
        ->assertSeeText(__('settlement.fields.approved_by'))
        ->assertSeeText($admin->name)
        ->assertSeeText(__('audit_timeline.section_title'))
        ->assertSeeText($actionLabels['withdrawal.approved'])
        ->assertSeeText(Carbon::parse('2026-10-12 18:50:00', 'UTC')->setTimezone($timezone)->translatedFormat('d M Y H:i').' '.$timezone)
        ->assertSeeText(Carbon::parse('2026-10-12 16:50:00', 'UTC')->setTimezone($timezone)->translatedFormat('d M Y H:i').' '.$timezone)
        ->assertDontSeeText(Carbon::parse('2026-10-12 18:50:00', 'UTC')->setTimezone($timezone)->translatedFormat('d M Y H:i').' e')
        ->assertDontSeeText(Carbon::parse('2026-10-12 16:50:00', 'UTC')->setTimezone($timezone)->translatedFormat('d M Y H:i').' e');

    $after = $withdrawal->refresh()->getRawOriginal();
    ksort($after);

    expect($after)->toBe($before);
})->with([
    ['en', 'Africa/Cairo'],
    ['ar', 'Asia/Dubai'],
]);

it('leaves approval absent for a pending withdrawal', function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_withdrawals::queue', 'view_withdrawals::queue'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin);
    $withdrawal = WithdrawalFactory::new()->pending()->create();
    $actionLabels = __('audit_timeline.action_keys');

    Livewire::test(ListWithdrawalsQueue::class)
        ->call('mountTableAction', 'view', (string) $withdrawal->id)
        ->assertSeeText(__('settlement.withdrawal.not_yet_approved'))
        ->assertDontSeeText($actionLabels['withdrawal.approved']);

    expect($withdrawal->fresh()->approved_at)->toBeNull()
        ->and($withdrawal->fresh()->approved_by_admin_id)->toBeNull();
});

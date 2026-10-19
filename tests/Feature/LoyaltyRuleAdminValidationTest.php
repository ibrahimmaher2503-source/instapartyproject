<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use App\Modules\Loyalty\Filament\Resources\LoyaltyRuleResource\Pages\ListLoyaltyRules;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('keeps the loyalty rule create modal open and shows required errors for empty submission', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_loyalty::rule', 'web'),
        Permission::findOrCreate('create_loyalty::rule', 'web'),
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $before = LoyaltyRule::query()->count();

    Livewire::test(ListLoyaltyRules::class)
        ->mountAction('create')
        ->callMountedAction()
        ->assertHasActionErrors([
            'loyalty_program_id' => 'required',
            'rule_kind' => 'required',
        ])
        ->assertActionMounted('create');

    expect(LoyaltyRule::query()->count())->toBe($before);
});

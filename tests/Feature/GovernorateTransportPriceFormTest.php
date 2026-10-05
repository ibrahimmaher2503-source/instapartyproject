<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Filament\Resources\GovernorateResource\Pages\EditGovernorate;
use App\Modules\Identity\Domain\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('edits transport prices in EGP while persisting integer minor units', function (string $locale): void {
    app()->setLocale($locale);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    foreach (['view_any_governorate', 'view_governorate', 'update_governorate'] as $name) {
        $admin->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $governorate = Governorate::factory()->create(['transport_fee_minor' => 2500]);
    Livewire::test(EditGovernorate::class, ['record' => $governorate->getRouteKey()])
        ->assertStatus(200)
        ->assertFormSet(['transport_fee_minor' => '25.00'])
        ->fillForm(['transport_fee_minor' => '37.50'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($governorate->fresh()->transport_fee_minor)->toBe(3750);
    Livewire::test(EditGovernorate::class, ['record' => $governorate->getRouteKey()])
        ->fillForm(['transport_fee_minor' => '1.001'])
        ->call('save')
        ->assertHasFormErrors(['transport_fee_minor' => 'decimal'])
        ->assertSee(__('geography.errors.transport_price_invalid'));
    expect($governorate->fresh()->transport_fee_minor)->toBe(3750);
})->with(['en', 'ar']);

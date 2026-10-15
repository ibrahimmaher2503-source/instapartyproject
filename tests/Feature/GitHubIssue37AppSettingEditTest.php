<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Models\AppSetting;
use App\Modules\Shared\Filament\Resources\AppSettingResource;
use App\Modules\Shared\Filament\Resources\AppSettingResource\Pages\EditAppSetting;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('loads scalar and structured JSON-cast settings in the authorized edit form', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $scalar = AppSetting::query()->create([
        'key' => 'booking_hold_minutes',
        'value' => 15,
    ]);
    $boolean = AppSetting::query()->create([
        'key' => 'escrow_banner_enabled',
        'value' => false,
    ]);
    $structured = AppSetting::query()->create([
        'key' => 'marketplace.currency',
        'value' => ['code' => 'EGP'],
    ]);
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_app::setting', 'web'),
        Permission::findOrCreate('view_app::setting', 'web'),
        Permission::findOrCreate('update_app::setting', 'web'),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->unsetRelation('permissions');

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get(AppSettingResource::getUrl('edit', ['record' => $scalar]))->assertOk();
    Livewire::test(EditAppSetting::class, ['record' => $scalar->getRouteKey()])
        ->assertFormSet(['_is_scalar' => true, 'value_text' => '15']);

    $this->get(AppSettingResource::getUrl('edit', ['record' => $boolean]))->assertOk();
    Livewire::test(EditAppSetting::class, ['record' => $boolean->getRouteKey()])
        ->assertFormSet(['_is_scalar' => true, 'value_text' => 'false']);

    $this->get(AppSettingResource::getUrl('edit', ['record' => $structured]))->assertOk();
    Livewire::test(EditAppSetting::class, ['record' => $structured->getRouteKey()])
        ->assertFormSet(['_is_scalar' => false, 'value' => ['code' => 'EGP']]);
});

it('preserves scalar and structured JSON value types when settings are saved', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $integer = AppSetting::query()->create(['key' => 'booking_hold_minutes', 'value' => 15]);
    $boolean = AppSetting::query()->create(['key' => 'escrow_banner_enabled', 'value' => false]);
    $string = AppSetting::query()->create(['key' => 'escrow_banner_text', 'value' => 'Secure escrow']);
    $structured = AppSetting::query()->create([
        'key' => 'marketplace.currency',
        'value' => ['code' => 'EGP'],
    ]);
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_app::setting', 'web'),
        Permission::findOrCreate('view_app::setting', 'web'),
        Permission::findOrCreate('update_app::setting', 'web'),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->unsetRelation('permissions');

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditAppSetting::class, ['record' => $integer->getRouteKey()])
        ->fillForm(['value_text' => '30'])
        ->call('save')
        ->assertHasNoFormErrors();
    Livewire::test(EditAppSetting::class, ['record' => $boolean->getRouteKey()])
        ->fillForm(['value_text' => 'true'])
        ->call('save')
        ->assertHasNoFormErrors();
    Livewire::test(EditAppSetting::class, ['record' => $string->getRouteKey()])
        ->fillForm(['value_text' => 'Updated escrow text'])
        ->call('save')
        ->assertHasNoFormErrors();
    Livewire::test(EditAppSetting::class, ['record' => $structured->getRouteKey()])
        ->fillForm(['value' => ['code' => 'USD']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($integer->fresh()->value)->toBe(30)
        ->and($boolean->fresh()->value)->toBeTrue()
        ->and($string->fresh()->value)->toBe('Updated escrow text')
        ->and($structured->fresh()->value)->toBe(['code' => 'USD']);
});

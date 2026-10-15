<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Region;
use App\Modules\Geography\Filament\Resources\CityResource\Pages\CreateCity;
use App\Modules\Geography\Filament\Resources\CityResource\Pages\EditCity;
use App\Modules\Geography\Filament\Resources\CityResource\Pages\ListCities;
use App\Modules\Identity\Domain\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_city', 'view_city', 'create_city', 'update_city', 'delete_city'] as $ability) {
        $admin->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('creates, moves, and deletes a city through the bilingual admin form and public selectors', function (): void {
    $firstRegion = Region::factory()->create();
    $secondRegion = Region::factory()->create();
    $name = 'QA City '.str()->ulid();

    Livewire::test(CreateCity::class)
        ->assertFormFieldExists('region_id')
        ->fillForm([
            'region_id' => (string) $firstRegion->id,
            'name.en' => $name,
            'name.ar' => 'مدينة اختبار '.str()->ulid(),
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->assertFormSet(['governorate_id' => $firstRegion->governorate_id])
        ->call('create')
        ->assertHasNoFormErrors();

    $city = City::query()->where('region_id', $firstRegion->id)->sole();

    expect($city->governorate_id)->toBe($firstRegion->governorate_id)
        ->and($city->getTranslation('name', 'en', false))->toBe($name);

    $this->withHeaders(['Accept-Language' => 'en'])
        ->getJson('/api/v1/customer/regions/'.$firstRegion->public_id.'/cities')
        ->assertOk()
        ->assertJsonPath('data.0.public_id', $city->public_id)
        ->assertJsonPath('data.0.name', $name);

    $updatedName = $name.' edited';
    Livewire::test(EditCity::class, ['record' => $city->getRouteKey()])
        ->assertFormSet(['region_id' => (string) $firstRegion->id])
        ->fillForm([
            'region_id' => (string) $secondRegion->id,
            'name.en' => $updatedName,
            'name.ar' => 'مدينة اختبار معدلة',
            'sort_order' => 2,
            'is_active' => true,
        ])
        ->assertFormSet(['governorate_id' => $secondRegion->governorate_id])
        ->call('save')
        ->assertHasNoFormErrors();

    $city->refresh();
    expect($city->region_id)->toBe($secondRegion->id)
        ->and($city->governorate_id)->toBe($secondRegion->governorate_id)
        ->and($city->getTranslation('name', 'en', false))->toBe($updatedName);

    $this->withHeaders(['Accept-Language' => 'en'])
        ->getJson('/api/v1/customer/regions/'.$firstRegion->public_id.'/cities')
        ->assertOk()
        ->assertJsonMissing(['public_id' => $city->public_id]);

    $this->withHeaders(['Accept-Language' => 'ar'])
        ->getJson('/api/v1/customer/regions/'.$secondRegion->public_id.'/cities')
        ->assertOk()
        ->assertJsonPath('data.0.public_id', $city->public_id)
        ->assertJsonPath('data.0.name', 'مدينة اختبار معدلة');

    Livewire::test(ListCities::class)
        ->callTableAction('delete', $city)
        ->callMountedAction()
        ->assertHasNoTableActionErrors();

    expect(City::query()->whereKey($city->id)->exists())->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\CloneServiceAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource\Pages\CreateVendorRentalService;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource\Pages\EditVendorRentalService;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function rentalDimensionsFixture(): array
{
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Rental)->create(['vendor_profile_id' => $vendor->id]);
    $vendor->user->givePermissionTo(Permission::findOrCreate('service.update.rental.own', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $vendor->user->unsetRelation('permissions');
    $root = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => ['rental']]);
    $child = Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => ['rental']]);

    return [$vendor, $root, $child];
}

function rentalDimensionsForm(Category $root, Category $child): array
{
    return ['name' => ['en' => 'Measured rental', 'ar' => 'خدمة تأجير بأبعاد'],
        'main_category_id' => $root->id, 'category_id' => $child->id, 'base_price_minor' => '250',
        'rentalDetail' => ['default_rental_duration_hours' => 4, 'length_m' => '4.50', 'width_m' => '3.25', 'height_m' => '2.00']];
}

it('creates edits clears and clones measured rentals through the native vendor form', function (string $locale): void {
    app()->setLocale($locale);
    [$vendor, $root, $child] = rentalDimensionsFixture();
    $this->actingAs($vendor->user, 'vendor');
    $form = Livewire::test(CreateVendorRentalService::class);
    foreach (['teardown_time_minutes', 'security_deposit_minor', 'minimum_space_sqm'] as $obsolete) {
        $form->assertFormFieldDoesNotExist('rentalDetail.'.$obsolete);
    }
    $form->fillForm(rentalDimensionsForm($root, $child))->call('create')->assertHasNoFormErrors();
    $service = Service::query()->sole();
    expect($service->rentalDetail->length_m)->toBe('4.50')
        ->and($service->rentalDetail->width_m)->toBe('3.25')->and($service->rentalDetail->height_m)->toBe('2.00');
    Livewire::test(EditVendorRentalService::class, ['record' => $service->public_id])
        ->assertFormSet(['rentalDetail.length_m' => '4.50', 'rentalDetail.width_m' => '3.25', 'rentalDetail.height_m' => '2.00'])
        ->fillForm(['rentalDetail.length_m' => '5.25', 'rentalDetail.height_m' => ''])
        ->call('save')->assertHasNoFormErrors();
    expect($service->refresh()->rentalDetail->length_m)->toBe('5.25')
        ->and($service->rentalDetail->width_m)->toBe('3.25')->and($service->rentalDetail->height_m)->toBeNull();
    Livewire::test(EditVendorRentalService::class, ['record' => $service->public_id])
        ->assertFormSet(['rentalDetail.length_m' => '5.25', 'rentalDetail.width_m' => '3.25', 'rentalDetail.height_m' => null]);
    $clone = app(CloneServiceAction::class)->execute($service, $vendor);
    expect($clone->rentalDetail->length_m)->toBe('5.25')->and($clone->rentalDetail->width_m)->toBe('3.25')
        ->and($clone->rentalDetail->height_m)->toBeNull();
})->with(['en', 'ar']);

it('keeps historical rental values intact and does not infer dimensions from floor space', function (string $locale): void {
    app()->setLocale($locale);
    [$vendor, , $child] = rentalDimensionsFixture();
    $service = Service::factory()->rental()->create(['vendor_profile_id' => $vendor->id, 'category_id' => $child->id]);
    ServiceRentalDetail::factory()->create(['service_id' => $service->id, 'teardown_time_minutes' => 75,
        'security_deposit_minor' => 45000, 'security_deposit_currency' => 'EGP', 'minimum_space_sqm' => 28]);
    $this->actingAs($vendor->user, 'vendor');
    $edit = Livewire::test(EditVendorRentalService::class, ['record' => $service->public_id])
        ->assertFormSet(['rentalDetail.length_m' => null, 'rentalDetail.width_m' => null, 'rentalDetail.height_m' => null]);
    foreach (['teardown_time_minutes', 'security_deposit_minor', 'minimum_space_sqm'] as $obsolete) {
        $edit->assertFormFieldDoesNotExist('rentalDetail.'.$obsolete);
    }
    $edit->call('save')->assertHasNoFormErrors();
    $edit->fillForm(['rentalDetail.length_m' => '4', 'rentalDetail.width_m' => '3', 'rentalDetail.height_m' => '2'])
        ->set('data.rentalDetail.teardown_time_minutes', 0)
        ->set('data.rentalDetail.security_deposit_minor', 0)
        ->set('data.rentalDetail.minimum_space_sqm', null)
        ->call('save')->assertHasNoFormErrors();
    $detail = $service->refresh()->rentalDetail;
    expect($detail->teardown_time_minutes)->toBe(75)->and($detail->security_deposit_minor)->toBe(45000)
        ->and($detail->security_deposit_currency)->toBe('EGP')->and($detail->minimum_space_sqm)->toBe(28)
        ->and($detail->length_m)->toBe('4.00')->and($detail->width_m)->toBe('3.00')->and($detail->height_m)->toBe('2.00');
})->with(['en', 'ar']);

it('rejects invalid dimensions during vendor create and edit', function (string $dimension, string $invalid): void {
    [$vendor, $root, $child] = rentalDimensionsFixture();
    $this->actingAs($vendor->user, 'vendor');
    $data = rentalDimensionsForm($root, $child);
    $data['rentalDetail'][$dimension] = $invalid;
    Livewire::test(CreateVendorRentalService::class)->fillForm($data)->call('create')
        ->assertHasFormErrors(['rentalDetail.'.$dimension]);
    expect(Service::query()->count())->toBe(0);
    $service = Service::factory()->rental()->create(['vendor_profile_id' => $vendor->id, 'category_id' => $child->id]);
    ServiceRentalDetail::factory()->create(['service_id' => $service->id, $dimension => '4.50']);
    Livewire::test(EditVendorRentalService::class, ['record' => $service->public_id])
        ->fillForm(['rentalDetail.'.$dimension => $invalid])->call('save')
        ->assertHasFormErrors(['rentalDetail.'.$dimension]);
    expect($service->refresh()->rentalDetail->{$dimension})->toBe('4.50');
})->with(['length_m', 'width_m', 'height_m'])->with(['negative' => '-1', 'zero' => '0', 'precision' => '4.501',
    'letters' => 'wide', 'exponent' => '1e2', 'overflow' => '1000000']);

it('roundtrips optional dimensions in existing vendor and customer APIs without changing money units', function (string $locale): void {
    [$vendor, , $child] = rentalDimensionsFixture();
    Sanctum::actingAs($vendor->user);
    $payload = ['name' => ['en' => 'API measured rental', 'ar' => 'تأجير بأبعاد عبر الخدمة'],
        'short_description' => ['en' => 'Measured dimensions', 'ar' => 'أبعاد محددة'],
        'category_id' => $child->id, 'base_price_minor' => 25000, 'default_rental_duration_hours' => 4,
        'length_m' => '4.50', 'width_m' => '3.25', 'height_m' => '2.00'];
    $response = $this->postJson('/api/v1/vendor/services/rental', $payload, ['Accept-Language' => $locale])
        ->assertCreated()->assertJsonPath('data.rental.length_m', '4.50')->assertJsonPath('data.rental.width_m', '3.25')
        ->assertJsonPath('data.rental.height_m', '2.00')->assertJsonPath('data.price_from_minor', 25000);
    $publicId = $response->json('data.public_id');
    $this->patchJson('/api/v1/vendor/services/rental/'.$publicId, ['length_m' => '5.25', 'height_m' => null])
        ->assertOk()->assertJsonPath('data.rental.length_m', '5.25')->assertJsonPath('data.rental.width_m', '3.25')
        ->assertJsonPath('data.rental.height_m', null);
    $service = Service::query()->where('public_id', $publicId)->sole();
    $service->forceFill(['status' => 'published'])->save();
    $this->getJson('/api/v1/customer/services/'.$publicId, ['Accept-Language' => $locale])
        ->assertOk()->assertJsonPath('data.rental.length_m', '5.25')->assertJsonPath('data.rental.width_m', '3.25')
        ->assertJsonPath('data.rental.height_m', null)->assertJsonPath('data.price_from_minor', 25000);
    $this->get('/'.$locale.'/services/'.$publicId)->assertOk()
        ->assertSee($locale === 'ar' ? 'الطول' : 'Length')->assertSee($locale === 'ar' ? 'العرض' : 'Width')
        ->assertSee('5.25 '.($locale === 'ar' ? 'متر' : 'm'))->assertDontSee($locale === 'ar' ? '<dt>الارتفاع</dt>' : '<dt>Height</dt>', false);
    $foreign = VendorProfile::factory()->approved()->create();
    Sanctum::actingAs($foreign->user);
    $this->patchJson('/api/v1/vendor/services/rental/'.$publicId, ['width_m' => '9'])->assertNotFound();
    expect($service->refresh()->rentalDetail->width_m)->toBe('3.25');
})->with(['en', 'ar']);

it('rejects invalid API dimensions before persistence', function (string $dimension, string $invalid): void {
    [$vendor, , $child] = rentalDimensionsFixture();
    $service = Service::factory()->rental()->create(['vendor_profile_id' => $vendor->id, 'category_id' => $child->id]);
    ServiceRentalDetail::factory()->create(['service_id' => $service->id, $dimension => '4.50']);
    Sanctum::actingAs($vendor->user);
    $this->postJson('/api/v1/vendor/services/rental', [
        'name' => ['en' => 'Invalid dimension', 'ar' => 'أبعاد غير صالحة'],
        'short_description' => ['en' => 'Test dimensions', 'ar' => 'اختبار الأبعاد'],
        'category_id' => $child->id, 'base_price_minor' => 25000, 'default_rental_duration_hours' => 4,
        $dimension => $invalid,
    ])->assertUnprocessable();
    expect(Service::query()->count())->toBe(1);
    $this->patchJson('/api/v1/vendor/services/rental/'.$service->public_id, [$dimension => $invalid])->assertUnprocessable();
    expect($service->refresh()->rentalDetail->{$dimension})->toBe('4.50');
})->with(['length_m', 'width_m', 'height_m'])->with(['-1', '0', '4.501', 'wide', '1e2', '1000000']);

it('upgrades the historical rental schema without modifying legacy values', function (): void {
    // This test uses the forced SQLite :memory: connection only.
    $service = Service::factory()->rental()->create();
    ServiceRentalDetail::factory()->create(['service_id' => $service->id, 'teardown_time_minutes' => 75,
        'security_deposit_minor' => 45000, 'minimum_space_sqm' => 28]);
    $migration = require app_path('Modules/Catalog/Database/Migrations/2026_10_05_000001_add_dimensions_to_service_rental_details_table.php');
    $migration->down();
    $migration->up();
    $detail = $service->refresh()->rentalDetail;
    expect($detail->length_m)->toBeNull()->and($detail->width_m)->toBeNull()->and($detail->height_m)->toBeNull()
        ->and($detail->teardown_time_minutes)->toBe(75)->and($detail->security_deposit_minor)->toBe(45000)
        ->and($detail->minimum_space_sqm)->toBe(28);
});

it('keeps existing API clients without dimensions compatible', function (): void {
    [$vendor, , $child] = rentalDimensionsFixture();
    Sanctum::actingAs($vendor->user);
    $this->postJson('/api/v1/vendor/services/rental', [
        'name' => ['en' => 'Legacy API rental', 'ar' => 'تأجير من الخدمة القديمة'],
        'short_description' => ['en' => 'No known dimensions', 'ar' => 'أبعاد غير محددة'],
        'category_id' => $child->id, 'base_price_minor' => 25000, 'default_rental_duration_hours' => 4,
        'teardown_time_minutes' => 75, 'security_deposit_minor' => 45000, 'minimum_space_sqm' => 28,
    ])->assertCreated()->assertJsonPath('data.rental.length_m', null)->assertJsonPath('data.rental.width_m', null)
        ->assertJsonPath('data.rental.height_m', null)->assertJsonPath('data.rental.teardown_time_minutes', 75)
        ->assertJsonPath('data.rental.security_deposit_minor', 45000)->assertJsonPath('data.rental.minimum_space_sqm', 28);
});

it('does not invent dimensions for historical rentals or expose them on other product types', function (string $locale, ProductType $type): void {
    $service = Service::factory()->published()->create(['product_type' => $type]);
    match ($type) {
        ProductType::Rental => ServiceRentalDetail::factory()->create(['service_id' => $service->id]),
        ProductType::Sale => ServiceSaleDetail::factory()->create(['service_id' => $service->id]),
        ProductType::Digital => ServiceDigitalDetail::factory()->create(['service_id' => $service->id]),
    };
    $response = $this->getJson('/api/v1/customer/services/'.$service->public_id, ['Accept-Language' => $locale])->assertOk();
    if ($type === ProductType::Rental) {
        $response->assertJsonPath('data.rental.length_m', null)->assertJsonPath('data.rental.width_m', null)->assertJsonPath('data.rental.height_m', null);
    } else {
        $response->assertJsonMissingPath('data.rental');
    }
    $this->get('/'.$locale.'/services/'.$service->public_id)->assertOk()
        ->assertDontSee($locale === 'ar' ? '<dt>الطول</dt>' : '<dt>Length</dt>', false);
})->with(['en', 'ar'])->with(ProductType::cases());

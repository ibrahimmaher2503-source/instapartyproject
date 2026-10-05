<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorServicesListPage;
use App\Modules\Catalog\Infrastructure\Repositories\EloquentCatalogServiceReader;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Brick\Money\Money;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function vendorServicePriceFixture(ProductType $type): array
{
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $profile = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $profile->id]);
    $profile->user->givePermissionTo(Permission::findOrCreate("service.update.{$type->value}.own", 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $profile->user->unsetRelation('permissions');
    $root = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => [$type->value]]);
    $child = Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => [$type->value]]);
    $classType = ucfirst($type->value);
    $base = "App\\Modules\\Catalog\\Filament\\Vendor\\Resources\\Vendor{$classType}ServiceResource\\Pages\\";

    return [$profile, $root, $child, $base."CreateVendor{$classType}Service", $base."EditVendor{$classType}Service", $base."ListVendor{$classType}Services"];
}

it('stores major EGP input as minor units and roundtrips create edit tables and the catalog reader', function (string $locale, ProductType $type): void {
    app()->setLocale($locale);
    [$profile, $root, $child, $create, $edit, $list] = vendorServicePriceFixture($type);
    $this->actingAs($profile->user, 'vendor');
    Livewire::test($create)->fillForm([
        'name' => ['en' => 'EGP price service', 'ar' => 'خدمة بسعر بالجنيه'],
        'main_category_id' => $root->id, 'category_id' => $child->id, 'base_price_minor' => '250',
        'rentalDetail' => ['default_rental_duration_hours' => 4], 'digitalDetail' => ['delivery_method' => 'email'],
    ])->call('create')->assertHasNoFormErrors();
    $service = Service::query()->sole();
    expect($service->base_price_minor)->toBe(25000)->and($service->base_price_currency)->toBe('EGP');
    $formatted = Money::of('250', 'EGP')->formatTo($locale === 'ar' ? 'ar_EG' : 'en_GB');
    foreach ([$list, VendorServicesListPage::class] as $page) {
        Livewire::test($page)->assertCanSeeTableRecords([$service])
            ->assertTableColumnFormattedStateSet('base_price_minor', $formatted, $service);
    }
    Livewire::test($edit, ['record' => $service->public_id])->assertFormSet(['base_price_minor' => '250.00'])
        ->call('save')->assertHasNoFormErrors();
    expect($service->refresh()->base_price_minor)->toBe(25000);
    Livewire::test($edit, ['record' => $service->public_id])->assertFormSet(['base_price_minor' => '250.00'])
        ->fillForm(['base_price_minor' => '250.50'])->call('save')->assertHasNoFormErrors();
    expect($service->refresh()->base_price_minor)->toBe(25050);
    Livewire::test($edit, ['record' => $service->public_id])->assertFormSet(['base_price_minor' => '250.50']);
    $service->forceFill(['status' => 'published'])->save();
    expect(app(EloquentCatalogServiceReader::class)->findPublishedById($service->id)->basePriceMinor)->toBe(25050);
})->with(['en', 'ar'])->with(ProductType::cases());

it('rejects invalid prices on create and edit without rounding or changing stored amounts', function (mixed $invalid, ProductType $type): void {
    [$profile, $root, $child, $create, $edit] = vendorServicePriceFixture($type);
    $this->actingAs($profile->user, 'vendor');
    $values = ['name' => ['en' => 'Invalid price', 'ar' => 'سعر غير صالح'], 'main_category_id' => $root->id,
        'category_id' => $child->id, 'base_price_minor' => $invalid,
        'rentalDetail' => ['default_rental_duration_hours' => 4], 'digitalDetail' => ['delivery_method' => 'email']];
    Livewire::test($create)->fillForm($values)->call('create')->assertHasFormErrors(['base_price_minor']);
    expect(Service::query()->count())->toBe(0);
    $service = Service::factory()->create(['vendor_profile_id' => $profile->id, 'category_id' => $child->id,
        'product_type' => $type, 'base_price_minor' => 25050, 'base_price_currency' => 'EGP']);
    Livewire::test($edit, ['record' => $service->public_id])->fillForm(['base_price_minor' => $invalid])
        ->call('save')->assertHasFormErrors(['base_price_minor']);
    expect($service->refresh()->base_price_minor)->toBe(25050);
})->with(['negative' => '-1', 'excess_precision' => '250.001', 'empty' => '', 'letters' => 'invalid',
    'thousands_separator' => '1,000', 'exponent' => '1e2', 'overflow' => '92233720368547758.08',
    'float' => 0.29])->with(ProductType::cases());

it('keeps the major amount intact after validation failure and retry', function (ProductType $type): void {
    [$profile, $root, $child, $create] = vendorServicePriceFixture($type);
    $this->actingAs($profile->user, 'vendor');
    Livewire::test($create)->fillForm([
        'name' => ['en' => 'Retry EGP input', 'ar' => ''], 'main_category_id' => $root->id,
        'category_id' => $child->id, 'base_price_minor' => '250.50',
        'rentalDetail' => ['default_rental_duration_hours' => 4], 'digitalDetail' => ['delivery_method' => 'email'],
    ])->call('create')->assertHasFormErrors(['name.ar'])->assertFormSet(['base_price_minor' => '250.50'])
        ->fillForm(['name.ar' => 'إعادة حفظ السعر'])->call('create')->assertHasNoFormErrors();
    expect(Service::query()->sole()->base_price_minor)->toBe(25050);
})->with(ProductType::cases());

it('preserves historical cents without converting them again on repeated saves', function (string $amount, int $minor, ProductType $type): void {
    [$profile, , $child, , $edit] = vendorServicePriceFixture($type);
    $this->actingAs($profile->user, 'vendor');
    $service = Service::factory()->create(['vendor_profile_id' => $profile->id, 'category_id' => $child->id,
        'product_type' => $type, 'base_price_minor' => $minor, 'base_price_currency' => 'EGP']);
    match ($type) {
        ProductType::Rental => ServiceRentalDetail::factory()->create(['service_id' => $service->id]),
        ProductType::Sale => ServiceSaleDetail::factory()->create(['service_id' => $service->id]),
        ProductType::Digital => ServiceDigitalDetail::factory()->create(['service_id' => $service->id, 'delivery_method' => 'email', 'has_expiry' => false]),
    };
    for ($save = 0; $save < 2; $save++) {
        Livewire::test($edit, ['record' => $service->public_id])->assertFormSet(['base_price_minor' => $amount])
            ->call('save')->assertHasNoFormErrors();
        expect($service->refresh()->base_price_minor)->toBe($minor);
    }
})->with(['one_cent' => ['0.01', 1], 'zero' => ['0.00', 0], 'fraction' => ['0.29', 29],
    'large_exact' => ['90071992547409.91', 9007199254740991]])->with(ProductType::cases());

it('does not silently convert a historical foreign-currency record to EGP', function (): void {
    [$profile, , $child, , $edit] = vendorServicePriceFixture(ProductType::Sale);
    $this->actingAs($profile->user, 'vendor');
    $service = Service::factory()->sale()->create(['vendor_profile_id' => $profile->id, 'category_id' => $child->id,
        'base_price_minor' => 250, 'base_price_currency' => 'JPY']);
    Livewire::test($edit, ['record' => $service->public_id])->assertFormSet(['base_price_minor' => '250'])
        ->call('save')->assertHasNoFormErrors();
    expect($service->refresh()->base_price_minor)->toBe(250)->and($service->base_price_currency)->toBe('JPY');
});

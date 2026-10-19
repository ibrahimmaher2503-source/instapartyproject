<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Application\DTOs\PolicyDecisionDto;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionPolicyContract;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function serviceCategoryPairFixture(ProductType $type): array
{
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $profile = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $profile->id]);
    $profile->user->givePermissionTo(Permission::findOrCreate("service.update.{$type->value}.own", 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $profile->user->unsetRelation('permissions');
    $root = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => [$type->value], 'sort_order' => 1]);
    $child = Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => [$type->value]]);
    $otherRoot = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => [$type->value], 'sort_order' => 2]);
    $otherChild = Category::factory()->create(['parent_id' => $otherRoot->id, 'allowed_product_types' => [$type->value]]);
    Category::factory()->create(['parent_id' => null, 'allowed_product_types' => [$type->value], 'sort_order' => 3]);
    $classType = ucfirst($type->value);
    $base = "App\\Modules\\Catalog\\Filament\\Vendor\\Resources\\Vendor{$classType}ServiceResource\\Pages\\";
    $policy = Mockery::mock(SubscriptionPolicyContract::class);
    $policy->shouldReceive('canCreateService')->andReturn(PolicyDecisionDto::allow());
    app()->instance(SubscriptionPolicyContract::class, $policy);

    return [$profile, $root, $child, $otherRoot, $otherChild, $base."CreateVendor{$classType}Service", $base."EditVendor{$classType}Service"];
}

it('lists only root categories with eligible children and clears stale child selections', function (string $locale, ProductType $type): void {
    app()->setLocale($locale);
    [$profile, $root, $child, $otherRoot, $otherChild, $create] = serviceCategoryPairFixture($type);
    $this->actingAs($profile->user, 'vendor');
    $component = Livewire::test($create)->assertFormFieldIsDisabled('category_id');
    $component->assertFormFieldExists('main_category_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$root->id, $otherRoot->id]);
    $component->set('data.main_category_id', $root->id)->assertFormFieldIsEnabled('category_id');
    $component->assertFormFieldExists('category_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$child->id]);
    $component->set('data.category_id', $child->id)->set('data.main_category_id', $otherRoot->id)
        ->assertFormSet(['category_id' => null]);
    $component->assertFormFieldExists('category_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$otherChild->id]);
})->with(['en', 'ar'])->with(ProductType::cases());

it('creates and edits a service through a validated category pair', function (string $locale, ProductType $type): void {
    app()->setLocale($locale);
    [$profile, $root, $child, $otherRoot, $otherChild, $create, $edit] = serviceCategoryPairFixture($type);
    $this->actingAs($profile->user, 'vendor');
    Livewire::test($create)->fillForm([
        'name' => ['en' => 'Valid pair service', 'ar' => 'خدمة بتصنيف صالح'],
        'main_category_id' => $root->id, 'category_id' => $child->id, 'base_price_minor' => '250',
        'rentalDetail' => ['default_rental_duration_hours' => 4],
        'digitalDetail' => ['delivery_method' => 'email'],
    ])->call('create')->assertHasNoFormErrors();
    $service = Service::query()->sole();
    expect($service->category_id)->toBe($child->id)->and($service->product_type)->toBe($type);
    expect($profile->user->can("service.update.{$type->value}.own", 'web'))->toBeTrue();
    expect(Filament::getCurrentPanel()->getId())->toBe('vendor');
    expect(Filament::auth()->id())->toBe($profile->user_id);
    expect(Gate::forUser($profile->user)->allows('update', $service))->toBeTrue();
    $component = Livewire::test($edit, ['record' => $service->public_id])->assertOk()->assertFormSet([
        'main_category_id' => $root->id, 'category_id' => $child->id,
    ]);
    $component->fillForm(['main_category_id' => $otherRoot->id, 'category_id' => $otherChild->id])
        ->call('save')->assertHasNoFormErrors();
    expect($service->refresh()->category_id)->toBe($otherChild->id);
})->with(['en', 'ar'])->with(ProductType::cases());

it('rejects tampered or unavailable category relationships on create and edit without changing records', function (string $case, ProductType $type): void {
    [$profile, $root, $child, $otherRoot, $otherChild, $create, $edit] = serviceCategoryPairFixture($type);
    $this->actingAs($profile->user, 'vendor');
    $invalidId = match ($case) {
        'wrong_parent' => $otherChild->id,
        'root_as_child' => $root->id,
        default => $child->id,
    };
    match ($case) {
        'inactive' => $child->update(['is_active' => false]),
        'wrong_type' => $child->update(['allowed_product_types' => []]),
        'deleted' => $child->delete(),
        default => null,
    };
    $values = ['name' => ['en' => 'Invalid service', 'ar' => 'خدمة غير صالحة'], 'main_category_id' => $root->id,
        'category_id' => $invalidId, 'base_price_minor' => '250', 'rentalDetail' => ['default_rental_duration_hours' => 4]];
    Livewire::test($create)->fillForm($values)->call('create')->assertHasFormErrors(['category_id']);
    expect(Service::query()->count())->toBe(0);
    $service = Service::factory()->create(['vendor_profile_id' => $profile->id, 'category_id' => $otherChild->id, 'product_type' => $type]);
    Livewire::test($edit, ['record' => $service->public_id])->fillForm($values)->call('save')->assertHasFormErrors(['category_id']);
    expect($service->refresh()->category_id)->toBe($otherChild->id);
})->with(['wrong_parent', 'root_as_child', 'inactive', 'wrong_type', 'deleted'])->with(ProductType::cases());

it('does not silently reclassify a historical root-category service when opening its edit form', function (ProductType $type): void {
    [$profile, $root, , , , , $edit] = serviceCategoryPairFixture($type);
    $this->actingAs($profile->user, 'vendor');
    $service = Service::factory()->create(['vendor_profile_id' => $profile->id, 'category_id' => $root->id, 'product_type' => $type]);
    Livewire::test($edit, ['record' => $service->public_id])->assertFormSet(['main_category_id' => $root->id, 'category_id' => null])
        ->call('save')->assertHasFormErrors(['category_id']);
    expect($service->refresh()->category_id)->toBe($root->id);
})->with(ProductType::cases());

it('validates the form-only main category even though it is not persisted', function (string $case, ProductType $type): void {
    [$profile, $root, $child, , $otherChild, $create, $edit] = serviceCategoryPairFixture($type);
    $this->actingAs($profile->user, 'vendor');
    match ($case) {
        'inactive' => $root->update(['is_active' => false]),
        'wrong_type' => $root->update(['allowed_product_types' => []]),
        'deleted' => $root->delete(),
        default => null,
    };
    $mainId = $case === 'missing' ? null : $root->id;
    $childId = $child->id;
    if ($case === 'nested') {
        $mainId = $child->id;
        $childId = Category::factory()->create(['parent_id' => $child->id, 'allowed_product_types' => [$type->value]])->id;
    }
    $values = ['name' => ['en' => 'Invalid main', 'ar' => 'تصنيف رئيسي غير صالح'], 'main_category_id' => $mainId,
        'category_id' => $childId, 'base_price_minor' => '250', 'rentalDetail' => ['default_rental_duration_hours' => 4]];
    Livewire::test($create)->fillForm($values)->call('create')->assertHasFormErrors(['main_category_id']);
    expect(Service::query()->count())->toBe(0);
    $service = Service::factory()->create(['vendor_profile_id' => $profile->id, 'category_id' => $otherChild->id, 'product_type' => $type]);
    Livewire::test($edit, ['record' => $service->public_id])->fillForm($values)->call('save')->assertHasFormErrors(['main_category_id']);
    expect($service->refresh()->category_id)->toBe($otherChild->id);
})->with(['inactive', 'wrong_type', 'deleted', 'missing', 'nested'])->with(ProductType::cases());

it('keeps foreign services inaccessible even with an own-service update permission', function (ProductType $type): void {
    [$profile, , $child] = serviceCategoryPairFixture($type);
    $foreign = VendorProfile::factory()->approved()->create([
        'primary_governorate_id' => $profile->primary_governorate_id, 'primary_city_id' => $profile->primary_city_id,
    ]);
    $service = Service::factory()->create(['vendor_profile_id' => $foreign->id, 'category_id' => $child->id, 'product_type' => $type]);
    $this->actingAs($profile->user, 'vendor');
    expect(Gate::forUser($profile->user)->allows('update', $service))->toBeFalse();
    $this->get("/vendor-portal/vendor-{$type->value}-services/{$service->public_id}/edit")->assertNotFound();
})->with(ProductType::cases());

it('keeps edit forbidden without a service update permission', function (ProductType $type): void {
    [$profile, , $child, , , , $edit] = serviceCategoryPairFixture($type);
    $profile->user->revokePermissionTo(Permission::findByName("service.update.{$type->value}.own", 'web'));
    $profile->user->unsetRelation('permissions');
    $this->actingAs($profile->user, 'vendor');
    $service = Service::factory()->create(['vendor_profile_id' => $profile->id, 'category_id' => $child->id, 'product_type' => $type]);
    Livewire::test($edit, ['record' => $service->public_id])->assertForbidden();
})->with(ProductType::cases());

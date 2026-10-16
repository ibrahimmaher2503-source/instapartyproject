<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\UpdateOccasionAction;
use App\Modules\Catalog\Application\DTOs\OccasionDTO;
use App\Modules\Catalog\Application\Listeners\ReindexServicesForOccasionCategoriesChanged;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Events\OccasionCategoriesChanged;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Resources\OccasionResource\Pages\CreateOccasion;
use App\Modules\Catalog\Filament\Resources\OccasionResource\Pages\EditOccasion;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Application\DTOs\PolicyDecisionDto;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionPolicyContract;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function batch07TaxonomyAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();

    foreach (['create_occasion', 'view_any_occasion', 'update_occasion'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return $admin;
}

function batch07VendorCreateFixture(ProductType $type): array
{
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $profile = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $profile->id]);
    $profile->user->givePermissionTo(Permission::findOrCreate("service.update.{$type->value}.own", 'web'));
    $classType = ucfirst($type->value);
    $page = "App\\Modules\\Catalog\\Filament\\Vendor\\Resources\\Vendor{$classType}ServiceResource\\Pages\\CreateVendor{$classType}Service";

    $policy = Mockery::mock(SubscriptionPolicyContract::class);
    $policy->shouldReceive('canCreateService')->andReturn(PolicyDecisionDto::allow());
    app()->instance(SubscriptionPolicyContract::class, $policy);

    return [$profile, $page];
}

it('distinguishes an omitted occasion category list from an explicit clear', function (): void {
    $attributes = [
        'code' => 'dto-occasion',
        'name' => ['en' => 'DTO occasion', 'ar' => 'مناسبة DTO'],
    ];

    expect(OccasionDTO::fromArray($attributes)->categoryIds)->toBeNull()
        ->and(OccasionDTO::fromArray($attributes + ['category_ids' => []])->categoryIds)->toBe([]);
});

it('locks the occasion and registers added and removed categories when scalar fields are unchanged', function (): void {
    $occasion = Occasion::factory()->create();
    $retainedCategory = Category::factory()->create(['allowed_product_types' => ['rental']]);
    $removedCategory = Category::factory()->create(['allowed_product_types' => ['rental']]);
    $addedCategory = Category::factory()->create(['allowed_product_types' => ['rental']]);
    $occasion->categories()->attach([
        $retainedCategory->id => ['sort_order' => 11],
        $removedCategory->id => ['sort_order' => 19],
    ]);
    Event::fake([OccasionCategoriesChanged::class]);

    $afterCommit = null;
    $database = Mockery::mock(app('db'))->makePartial();
    DB::swap($database);
    $database
        ->shouldReceive('afterCommit')
        ->once()
        ->with(Mockery::on(function ($callback) use (&$afterCommit): bool {
            if (! is_callable($callback)) {
                return false;
            }

            $afterCommit = $callback;

            return true;
        }));

    app(UpdateOccasionAction::class)->execute($occasion, new OccasionDTO(
        code: $occasion->code,
        name: $occasion->getTranslations('name'),
        categoryIds: [$retainedCategory->id, $addedCategory->id],
    ));

    expect($afterCommit)->toBeCallable();
    $afterCommit();

    Event::assertDispatched(OccasionCategoriesChanged::class, fn (OccasionCategoriesChanged $event): bool => $event->categoryIds === [
        $removedCategory->id,
        $addedCategory->id,
    ]);
});

it('binds the occasion category event to a Scout refresh of affected published services', function (): void {
    $includedCategory = Category::factory()->create(['allowed_product_types' => ['rental']]);
    $addedCategory = Category::factory()->create(['allowed_product_types' => ['rental']]);
    $unaffectedCategory = Category::factory()->create(['allowed_product_types' => ['rental']]);
    $included = Service::factory()->rental()->published()->create(['category_id' => $includedCategory->id]);
    $alsoIncluded = Service::factory()->rental()->published()->create(['category_id' => $addedCategory->id]);
    Service::factory()->rental()->published()->create(['category_id' => $unaffectedCategory->id]);
    Service::factory()->rental()->create(['category_id' => $includedCategory->id]);

    config(['scout.driver' => 'batch07-taxonomy-engine', 'scout.queue' => false]);
    $engine = Mockery::mock(Engine::class);
    $engine->shouldReceive('update')
        ->once()
        ->with(Mockery::on(fn (EloquentCollection $services): bool => $services->count() === 2
            && array_diff($services->modelKeys(), [$included->id, $alsoIncluded->id]) === []));
    app(EngineManager::class)->extend('batch07-taxonomy-engine', fn () => $engine);

    expect(Event::hasListeners(OccasionCategoriesChanged::class))->toBeTrue();

    app(ReindexServicesForOccasionCategoriesChanged::class)->handle(
        new OccasionCategoriesChanged([$includedCategory->id, $addedCategory->id]),
    );
});

it('links occasion categories through the admin form and preserves retained pivot order', function (): void {
    $admin = batch07TaxonomyAdmin();
    $root = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => ['rental']]);
    $child = Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => ['rental']]);
    $inactiveChild = Category::factory()->create([
        'parent_id' => $root->id,
        'allowed_product_types' => ['rental'],
        'is_active' => false,
    ]);
    $removedChild = Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => ['rental']]);
    $addedChild = Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => ['rental']]);
    $this->actingAs($admin);

    Livewire::test(CreateOccasion::class)
        ->fillForm([
            'code' => 'linked-occasion',
            'name.en' => 'Linked occasion',
            'name.ar' => 'مناسبة مرتبطة',
            'category_ids' => [$child->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $occasion = Occasion::query()->where('code', 'linked-occasion')->firstOrFail();
    expect(DB::table('occasion_category')->where('occasion_id', $occasion->id)->pluck('category_id')->map(fn ($id): int => (int) $id)->all())
        ->toBe([$child->id]);

    $occasion->categories()->attach([
        $inactiveChild->id => ['sort_order' => 23],
        $removedChild->id => ['sort_order' => 41],
    ]);
    DB::table('occasion_category')->where('occasion_id', $occasion->id)->where('category_id', $child->id)->update(['sort_order' => 17]);

    Livewire::test(EditOccasion::class, ['record' => $occasion->public_id])
        ->fillForm(['category_ids' => [$child->id, $inactiveChild->id, $addedChild->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    $pivot = DB::table('occasion_category')->where('occasion_id', $occasion->id)->orderBy('category_id')->get()->keyBy('category_id');
    expect($pivot->keys()->map(fn ($id): int => (int) $id)->all())->toEqualCanonicalizing([$child->id, $inactiveChild->id, $addedChild->id])
        ->and((int) $pivot[$child->id]->sort_order)->toBe(17)
        ->and((int) $pivot[$inactiveChild->id]->sort_order)->toBe(23)
        ->and((int) $pivot[$addedChild->id]->sort_order)->toBe(0);
});

it('rejects category IDs that are not active eligible direct subcategories', function (string $case): void {
    $admin = batch07TaxonomyAdmin();
    $root = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => ['rental']]);
    $candidate = match ($case) {
        'inactive_child' => Category::factory()->create([
            'parent_id' => $root->id,
            'allowed_product_types' => ['rental'],
            'is_active' => false,
        ]),
        'type_mismatch' => Category::factory()->create([
            'parent_id' => $root->id,
            'allowed_product_types' => ['sale'],
        ]),
        'top_level' => Category::factory()->create(['parent_id' => null, 'allowed_product_types' => ['rental']]),
        'inactive_parent' => tap(
            Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => ['rental']]),
            fn (Category $child) => $root->update(['is_active' => false]),
        ),
    };
    $this->actingAs($admin);

    Livewire::test(CreateOccasion::class)
        ->fillForm([
            'code' => 'invalid-link-occasion',
            'name.en' => 'Invalid link',
            'name.ar' => 'رابط غير صالح',
            'category_ids' => [$candidate->id],
        ])
        ->call('create')
        ->assertHasFormErrors(['category_ids']);

    expect(Occasion::query()->where('code', 'invalid-link-occasion')->exists())->toBeFalse();
})->with(['inactive_child', 'type_mismatch', 'top_level', 'inactive_parent']);

it('shows only main categories with an active compatible subcategory and localized guidance', function (string $locale, ProductType $type): void {
    app()->setLocale($locale);
    config(['catalog.digital_services_enabled' => true]);
    [$profile, $page] = batch07VendorCreateFixture($type);
    $eligibleRoot = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => [$type->value]]);
    $eligibleChild = Category::factory()->create(['parent_id' => $eligibleRoot->id, 'allowed_product_types' => [$type->value]]);

    $emptyRoot = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => [$type->value]]);
    Category::factory()->create(['parent_id' => $emptyRoot->id, 'allowed_product_types' => [$type->value], 'is_active' => false]);

    $wrongTypeRoot = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => [$type->value]]);
    Category::factory()->create([
        'parent_id' => $wrongTypeRoot->id,
        'allowed_product_types' => [$type === ProductType::Rental ? ProductType::Sale->value : ProductType::Rental->value],
    ]);

    $this->actingAs($profile->user, 'vendor');
    Livewire::test($page)
        ->assertFormFieldExists('main_category_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$eligibleRoot->id]
            && $field->getHelperText() === __('catalog.vendor_category_requires_subcategory_help'))
        ->fillForm(['main_category_id' => $eligibleRoot->id])
        ->assertFormFieldExists('category_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$eligibleChild->id]);
})->with(['en', 'ar'])->with(ProductType::cases());

<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\ListPublicOccasionsAction;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Filament\Resources\OccasionResource\Pages\CreateOccasion;
use App\Modules\Catalog\Filament\Resources\OccasionResource\Pages\EditOccasion;
use App\Modules\Catalog\Filament\Resources\OccasionResource\Pages\ListOccasions;
use App\Modules\Discovery\Domain\Contracts\PackageRecommendationReader;
use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use App\Modules\Discovery\Filament\Resources\PackageRecommendationResource\Pages\CreatePackageRecommendation;
use App\Modules\Discovery\Filament\Resources\PackageRecommendationResource\Pages\EditPackageRecommendation;
use App\Modules\Discovery\Filament\Resources\PackageRecommendationResource\Pages\ListPackageRecommendations;
use App\Modules\Identity\Domain\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['occasion', 'package::recommendation'] as $entity) {
        foreach (['view_any', 'view', 'create', 'update', 'delete'] as $prefix) {
            $admin->givePermissionTo(Permission::findOrCreate($prefix.'_'.$entity, 'web'));
        }
    }
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('creates edits and deletes an occasion through the admin forms and updates the public list', function (string $locale): void {
    app()->setLocale($locale);
    Livewire::test(CreateOccasion::class)->fillForm([
        'code' => 'qa-celebration', 'name.en' => 'QA Celebration', 'name.ar' => 'مناسبة للاختبار',
        'description.en' => 'A test celebration', 'description.ar' => 'مناسبة تجريبية',
        'sort_order' => 3, 'is_active' => true,
    ])->call('create')->assertHasNoFormErrors();
    $occasion = Occasion::where('code', 'qa-celebration')->firstOrFail();
    expect($occasion->getTranslations('name'))->toMatchArray(['en' => 'QA Celebration', 'ar' => 'مناسبة للاختبار']);
    expect(app(ListPublicOccasionsAction::class)->execute()->pluck('public_id'))->toContain($occasion->public_id);

    Livewire::test(EditOccasion::class, ['record' => $occasion->public_id])
        ->fillForm(['name.en' => 'Edited Celebration', 'name.ar' => 'مناسبة معدلة', 'is_active' => false])
        ->call('save')->assertHasNoFormErrors();
    expect($occasion->refresh()->getTranslations('name'))->toMatchArray(['en' => 'Edited Celebration', 'ar' => 'مناسبة معدلة']);
    expect(app(ListPublicOccasionsAction::class)->execute()->pluck('public_id'))->not->toContain($occasion->public_id);

    Livewire::test(EditOccasion::class, ['record' => $occasion->public_id])->callAction('delete');
    $this->assertSoftDeleted('occasions', ['id' => $occasion->id]);
    Livewire::test(ListOccasions::class)->assertCanNotSeeTableRecords([$occasion]);
})->with(['en', 'ar']);

it('creates edits publishes and deletes a package linked to an occasion', function (string $locale): void {
    app()->setLocale($locale);
    $occasion = Occasion::factory()->create();
    Livewire::test(CreatePackageRecommendation::class)->fillForm([
        'name.en' => 'QA Package', 'name.ar' => 'باقة للاختبار',
        'description.en' => 'Cake and balloons', 'description.ar' => 'كيك وبالونات',
        'slug' => 'qa-package', 'occasion_id' => $occasion->id,
        'display_order' => 1, 'is_published' => false,
        'min_budget_minor' => 250000, 'max_budget_minor' => 450000, 'budget_currency' => 'EGP',
    ])->call('create')->assertHasNoFormErrors();
    $package = PackageRecommendation::where('slug', 'qa-package')->firstOrFail();
    expect($package->getTranslations('name'))->toMatchArray(['en' => 'QA Package', 'ar' => 'باقة للاختبار']);
    expect(app(PackageRecommendationReader::class)->publishedForHomepage($locale))->toBeEmpty();

    Livewire::test(EditPackageRecommendation::class, ['record' => $package->public_id])
        ->fillForm(['name.en' => 'Edited Package', 'name.ar' => 'باقة معدلة', 'is_published' => true])
        ->call('save')->assertHasNoFormErrors();
    $published = collect(app(PackageRecommendationReader::class)->publishedForHomepage($locale))->firstWhere('public_id', $package->public_id);
    expect($published['name'])->toBe($locale === 'ar' ? 'باقة معدلة' : 'Edited Package')
        ->and($package->refresh()->occasion_id)->toBe($occasion->id);

    Livewire::test(ListPackageRecommendations::class)->searchTable('Edited Package')->assertCanSeeTableRecords([$package]);
    Livewire::test(EditPackageRecommendation::class, ['record' => $package->public_id])->callAction('delete');
    $this->assertDatabaseMissing('package_recommendations', ['id' => $package->id]);
    expect(app(PackageRecommendationReader::class)->publishedForHomepage($locale))->toBeEmpty();
})->with(['en', 'ar']);

it('validates required translations duplicate codes and invalid budgets before saving', function (): void {
    Occasion::factory()->create(['code' => 'existing-occasion']);
    Livewire::test(CreateOccasion::class)->fillForm([
        'code' => 'existing-occasion', 'name.en' => '', 'name.ar' => '',
    ])->call('create')->assertHasFormErrors(['code' => 'unique', 'name.en' => 'required', 'name.ar' => 'required']);
    Livewire::test(CreatePackageRecommendation::class)->fillForm([
        'name.en' => '', 'name.ar' => '', 'description.en' => '', 'description.ar' => '',
        'slug' => 'invalid-budget', 'min_budget_minor' => 250000, 'max_budget_minor' => 100000,
    ])->call('create')->assertHasFormErrors([
        'name.en' => 'required', 'name.ar' => 'required',
        'description.en' => 'required', 'description.ar' => 'required', 'max_budget_minor' => 'gte',
    ]);
    expect(PackageRecommendation::count())->toBe(0);
});

it('excludes deleted and inactive occasions from new package choices', function (): void {
    $active = Occasion::factory()->create(['is_active' => true]);
    $inactive = Occasion::factory()->create(['is_active' => false]);
    $deleted = Occasion::factory()->create(['is_active' => true]);
    $deleted->delete();
    $component = Livewire::test(CreatePackageRecommendation::class);
    $field = collect($component->instance()->form->getFlatComponents())->first(
        fn ($field) => $field instanceof Select && $field->getName() === 'occasion_id',
    );
    expect(array_keys($field->getOptions()))->toContain($active->id)
        ->not->toContain($inactive->id)
        ->not->toContain($deleted->id);
});

it('does not expose create edit or delete controls to an administrator with view permission only', function (): void {
    $reader = User::factory()->asAdmin()->create();
    foreach (['view_any_occasion', 'view_any_package::recommendation'] as $permission) {
        $reader->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($reader);
    $occasion = Occasion::factory()->create();
    $package = PackageRecommendation::create([
        'name' => ['en' => 'Read only', 'ar' => 'عرض فقط'],
        'description' => ['en' => 'Read only package', 'ar' => 'باقة للعرض فقط'], 'slug' => 'read-only',
    ]);
    Livewire::test(ListOccasions::class)->assertTableActionHidden('edit', $occasion)->assertTableActionHidden('delete', $occasion);
    Livewire::test(ListPackageRecommendations::class)->assertTableActionHidden('edit', $package)->assertTableActionHidden('delete', $package);
    Livewire::test(CreateOccasion::class)->assertForbidden();
    Livewire::test(CreatePackageRecommendation::class)->assertForbidden();
});

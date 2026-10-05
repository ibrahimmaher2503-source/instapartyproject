<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use Illuminate\Support\Str;

it('opens a published package from its search result card', function (): void {
    $category = Category::factory()->active()->create(['code' => 'select-package-category']);
    $occasion = Occasion::factory()->active()->create(['code' => 'select-package-occasion']);
    $category->occasions()->attach($occasion->id, ['sort_order' => 1]);
    Service::factory()->published()->create(['category_id' => $category->id]);

    $package = PackageRecommendation::query()->create([
        'public_id' => (string) Str::ulid(),
        'slug' => 'selectable-package',
        'name' => ['en' => 'Selectable package', 'ar' => 'باقة قابلة للاختيار'],
        'description' => ['en' => 'A package with a working next step.', 'ar' => 'باقة بخطوة تالية تعمل.'],
        'occasion_id' => $occasion->id,
        'min_budget_minor' => 125000,
        'max_budget_minor' => 275000,
        'budget_currency' => 'EGP',
        'is_published' => true,
        'display_order' => 1,
    ]);

    $this->get('/en/search?category_slug='.$category->code)
        ->assertOk()
        ->assertSee('/en/packages/'.$package->public_id, false);

    $this->get('/en/packages/'.$package->public_id)
        ->assertOk()
        ->assertSee('data-package-details', false)
        ->assertSee('Selectable package')
        ->assertSee('A package with a working next step.')
        ->assertSee('/en/wizard?occasion=select-package-occasion', false);
});

it('returns to search with a visible error when a package cannot be loaded', function (): void {
    $response = $this->get('/ar/packages/'.Str::ulid());

    $response
        ->assertRedirect('/ar/search')
        ->assertSessionHas('package_error', __('storefront.package.load_error'));

    $this->followingRedirects()
        ->get('/ar/packages/'.Str::ulid())
        ->assertOk()
        ->assertSee('data-package-error', false)
        ->assertSee(__('storefront.package.load_error'));
});

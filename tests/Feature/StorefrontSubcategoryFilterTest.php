<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;

it('combines dependent subcategories with vendor type and pagination in both locales', function (string $locale): void {
    $parent = Category::factory()->create(['code' => 'qa-parent']);
    $child = Category::factory()->create(['code' => 'qa-child', 'parent_id' => $parent->id]);
    $sibling = Category::factory()->create(['code' => 'qa-sibling', 'parent_id' => $parent->id]);
    $vendor = VendorProfile::factory()->approved()->create();
    Service::factory()->rental()->published()->count(13)->create(['vendor_profile_id' => $vendor->id, 'category_id' => $child->id]);
    Service::factory()->rental()->published()->create(['vendor_profile_id' => $vendor->id, 'category_id' => $sibling->id]);
    Service::factory()->sale()->published()->create(['vendor_profile_id' => $vendor->id, 'category_id' => $child->id]);
    Service::factory()->rental()->published()->create(['category_id' => $child->id]);
    $query = 'category_slug=qa-parent&subcategory=qa-child&product_type=rental&vendor='.$vendor->public_id;
    $response = $this->get("/{$locale}/search?".$query)->assertOk()->assertSee('data-search-subcategory', false);
    $page = $response->viewData('services');
    expect($page->total())->toBe(13)
        ->and(collect($page->items())->every(fn ($service) => $service->category_id === $child->id && $service->vendor_profile_id === $vendor->id && $service->product_type->value === 'rental'))->toBeTrue();
    $response->assertSee('value="qa-child" selected', false);
    expect($page->nextPageUrl())->toContain('subcategory=qa-child');
    expect($this->get("/{$locale}/search?".$query.'&page=2')->assertOk()->viewData('services')->count())->toBe(1);
    expect($this->get("/{$locale}/search?category_slug=qa-parent")->assertOk()->viewData('services')->total())->toBe(16);
    $this->get("/{$locale}/search?category_slug=qa-sibling&subcategory=qa-child")->assertRedirect()->assertSessionHasErrors('search');
    $this->get("/{$locale}/search?subcategory=qa-child")->assertRedirect()->assertSessionHasErrors('search');
    $child->update(['is_active' => false]);
    $this->get("/{$locale}/search?category_slug=qa-parent&subcategory=qa-child")->assertRedirect()->assertSessionHasErrors('search');
})->with(['en', 'ar']);

it('preserves old direct child category links and disables empty child selectors', function (): void {
    $parent = Category::factory()->create(['code' => 'qa-root']);
    $child = Category::factory()->create(['code' => 'qa-leaf', 'parent_id' => $parent->id]);
    Service::factory()->rental()->published()->create(['category_id' => $child->id]);
    $response = $this->get('/en/search?category_slug=qa-leaf')->assertOk();
    expect($response->viewData('filters'))->toMatchArray(['category_slug' => 'qa-root', 'subcategory' => 'qa-leaf']);
    expect($response->viewData('services')->total())->toBe(1);
    $empty = Category::factory()->create(['code' => 'qa-empty']);
    $this->get('/en/search?category_slug='.$empty->code)->assertOk()->assertSee('data-search-subcategory disabled', false);
});

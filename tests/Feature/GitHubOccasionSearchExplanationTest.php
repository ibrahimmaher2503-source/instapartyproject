<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;

it('explains an unmatched occasion and keeps unrelated services available without filters', function (string $locale): void {
    config(['scout.driver' => null, 'catalog.digital_services_enabled' => true]);
    $occasion = Occasion::factory()->active()->create();
    $unrelated = Service::factory()->sale()->published()->create();

    $response = $this->get("/{$locale}/search?occasion=".$occasion->code)->assertOk();
    expect($response->viewData('services')->total())->toBe(0);
    $response->assertSee(__('storefront.search.empty_occasion_body'))
        ->assertSee(__('storefront.search.empty_action'))
        ->assertSee('href="'.route('storefront.search').'"', false);

    $unfiltered = $this->get("/{$locale}/search")->assertOk();
    expect(collect($unfiltered->viewData('services')->items())->pluck('public_id')->all())
        ->toBe([$unrelated->public_id]);
    $unfiltered->assertDontSee(__('storefront.search.empty_occasion_body'));
})->with(['en', 'ar']);

it('keeps the general empty explanation when no occasion is selected', function (string $locale): void {
    config(['scout.driver' => null]);

    $this->get("/{$locale}/search")->assertOk()
        ->assertSee(__('storefront.search.empty_body'))
        ->assertDontSee(__('storefront.search.empty_occasion_body'));
})->with(['en', 'ar']);

it('returns mapped published rental sale and digital services instead of the empty explanation', function (string $locale): void {
    config(['scout.driver' => null, 'catalog.digital_services_enabled' => true]);
    $occasion = Occasion::factory()->active()->create();
    $category = Category::factory()->active()->create(['allowed_product_types' => ['rental', 'sale', 'digital']]);
    $category->occasions()->attach($occasion->id);
    $expectedIds = [];

    foreach (['rental', 'sale', 'digital'] as $type) {
        $expectedIds[] = Service::factory()->{$type}()->published()->create(['category_id' => $category->id])->public_id;
    }
    Service::factory()->sale()->published()->create();
    Service::factory()->sale()->create(['category_id' => $category->id]);

    $response = $this->get("/{$locale}/search?occasion=".$occasion->code)->assertOk();
    expect($response->viewData('services')->total())->toBe(3)
        ->and(collect($response->viewData('services')->items())->pluck('public_id')->sort()->values()->all())
        ->toBe(collect($expectedIds)->sort()->values()->all());
    $response->assertDontSee(__('storefront.search.empty_occasion_body'));
})->with(['en', 'ar']);

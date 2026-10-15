<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\VendorProfile;

function githubPlannerContext(): array
{
    $occasion = Occasion::factory()->active()->create(['code' => 'github-planner-birthday']);
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);

    return [$occasion, $governorate, $city, [
        'occasion' => $occasion->code,
        'governorate_public_id' => $governorate->public_id,
        'city_public_id' => $city->public_id,
        'event_starts_at' => now()->addMonths(5)->format('Y-m-d').'T12:00',
        'guest_count' => 10000,
    ]];
}

it('returns mapped eligible rental sale and digital services through the occasion and planner routes', function (string $locale): void {
    config(['scout.driver' => null, 'catalog.digital_services_enabled' => true]);
    [$occasion, $governorate, $city, $context] = githubPlannerContext();
    $category = Category::factory()->active()->create(['allowed_product_types' => ['rental', 'sale', 'digital']]);
    $category->occasions()->attach($occasion->id);
    $vendor = VendorProfile::factory()->approved()->create([
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
    ]);

    foreach (['rental', 'sale', 'digital'] as $type) {
        Service::factory()->{$type}()->published()->create([
            'category_id' => $category->id,
            'vendor_profile_id' => $vendor->id,
        ]);
    }
    $blocked = Service::factory()->rental()->published()->create([
        'category_id' => $category->id,
        'vendor_profile_id' => $vendor->id,
    ]);
    $blocked->availabilityBlocks()->create([
        'starts_at' => $context['event_starts_at'],
        'ends_at' => $context['event_starts_at'],
    ]);
    Service::factory()->sale()->published()->create(['vendor_profile_id' => $vendor->id]);
    Service::factory()->sale()->create(['category_id' => $category->id, 'vendor_profile_id' => $vendor->id]);

    $occasionResponse = $this->get("/{$locale}/search?occasion=".$occasion->code)->assertOk();
    expect($occasionResponse->viewData('services')->total())->toBe(4);

    $redirect = $this->post("/{$locale}/wizard/context", $context)->assertRedirect()->assertSessionHasNoErrors();
    $response = $this->get($redirect->headers->get('Location'))->assertOk();
    expect($response->viewData('services')->total())->toBe(3)
        ->and(collect($response->viewData('services')->items())->pluck('product_type')->map(fn ($type) => $type->value)->sort()->values()->all())
        ->toBe(['digital', 'rental', 'sale']);
})->with(['en', 'ar']);

it('explains an occasion with no eligible catalog mapping without showing unrelated services', function (string $locale): void {
    config(['scout.driver' => null]);
    [$occasion] = githubPlannerContext();
    Service::factory()->sale()->published()->create();

    $response = $this->get("/{$locale}/search?occasion=".$occasion->code)->assertOk();
    expect($response->viewData('services')->total())->toBe(0);
    $response->assertSee(__('storefront.search.empty_occasion_body'))
        ->assertSee(__('storefront.search.empty_action'));
})->with(['en', 'ar']);

it('documents the guest bound and enforces it on the wizard and direct search', function (string $locale): void {
    [, , , $context] = githubPlannerContext();
    $this->get("/{$locale}/wizard")->assertOk()
        ->assertSee('max="10000"', false)
        ->assertSee(__('storefront.wizard.step3.guests_max_hint'));

    foreach ([0, 10001, 99999999999, '1.5'] as $count) {
        $this->post("/{$locale}/wizard/context", [...$context, 'guest_count' => $count])
            ->assertRedirect()->assertSessionHasErrors('guest_count');
        $this->get("/{$locale}/search?guest_count=".$count)
            ->assertRedirect()->assertSessionHasErrors('search');
    }

    $this->post("/{$locale}/wizard/context", $context)
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->get("/{$locale}/search?guest_count=10000")->assertOk();
})->with(['en', 'ar']);

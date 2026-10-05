<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;

it('offers only vendors with a published service matching category and occasion together', function (): void {
    config(['scout.driver' => 'database']);

    $birthday = Occasion::factory()->create(['code' => 'vendor-filter-birthday']);
    $wedding = Occasion::factory()->create(['code' => 'vendor-filter-wedding']);
    $cakes = Category::factory()->create(['code' => 'vendor-filter-cakes']);
    $venues = Category::factory()->create(['code' => 'vendor-filter-venues']);
    $cakes->occasions()->attach($birthday);
    $venues->occasions()->attach($wedding);

    $cakeVendor = VendorProfile::factory()->approved()->create();
    $venueVendor = VendorProfile::factory()->approved()->create();
    Service::factory()->published()->create([
        'vendor_profile_id' => $cakeVendor->id,
        'category_id' => $cakes->id,
        'name' => ['en' => 'Matching Cake Service', 'ar' => 'خدمة كيك مطابقة'],
    ]);
    Service::factory()->published()->create([
        'vendor_profile_id' => $venueVendor->id,
        'category_id' => $venues->id,
        'name' => ['en' => 'Other Venue Service', 'ar' => 'خدمة قاعة أخرى'],
    ]);

    $matching = $this->get('/en/search?category_slug='.$cakes->code.'&occasion='.$birthday->code);
    $matching
        ->assertOk()
        ->assertSee('<option value="'.$cakeVendor->public_id.'"', false)
        ->assertDontSee('<option value="'.$venueVendor->public_id.'"', false)
        ->assertSee('Matching Cake Service')
        ->assertDontSee('Other Venue Service');

    $this->get('/en/search?category_slug='.$cakes->code.'&occasion='.$wedding->code.'&vendor='.$venueVendor->public_id)
        ->assertOk()
        ->assertDontSee('<option value="'.$venueVendor->public_id.'" selected', false)
        ->assertSeeText(__('storefront.search.empty_title'));
});

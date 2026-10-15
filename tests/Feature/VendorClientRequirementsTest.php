<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Infrastructure\Repositories\ServiceRecommendationRepository;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Application\Services\StorefrontText;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('searches names in both languages within this vendor and type and preserves search pagination', function (string $locale): void {
    $vendor = VendorProfile::factory()->approved()->create();
    Service::factory()->rental()->published()->count(16)->create(['vendor_profile_id' => $vendor->id, 'name' => ['en' => 'Party Lantern', 'ar' => 'فانوس الحفلة']]);
    Service::factory()->sale()->published()->create(['vendor_profile_id' => $vendor->id, 'name' => ['en' => 'Party Lantern', 'ar' => 'فانوس الحفلة']]);
    Service::factory()->rental()->published()->create(['vendor_profile_id' => $vendor->id, 'name' => ['en' => 'Other service', 'ar' => 'خدمة أخرى']]);
    Service::factory()->rental()->published()->create(['name' => ['en' => 'Party Lantern', 'ar' => 'فانوس الحفلة']]);
    $url = "/{$locale}/vendors/{$vendor->slug}";
    foreach (['Lantern', 'فانوس'] as $term) {
        $response = $this->get($url.'?type=rental&q='.urlencode($term))->assertOk();
        $page = $response->viewData('services');
        expect(collect($page->items())->every(fn ($service) => $service->vendor_profile_id === $vendor->id && $service->product_type->value === 'rental' && str_contains($service->getTranslation('name', 'en'), 'Lantern')))->toBeTrue();
        expect($page->nextPageUrl())->toContain('q=')->toContain('type=rental');
        expect($this->get($page->nextPageUrl())->assertOk()->viewData('services')->count())->toBe(1);
        $response->assertSee('data-vendor-text-search', false)->assertDontSee('<details>', false);
    }
    expect($this->get($url.'?q=not-found')->assertOk()->viewData('services'))->toBeEmpty();
    expect($this->get($url.'?q=%20%20')->assertOk()->viewData('services')->count())->toBe(15);
    $this->getJson($url.'?q[]=invalid')->assertUnprocessable();
})->with(['en', 'ar']);

it('filters only this vendors services and preserves type through pagination', function (string $locale): void {
    $vendor = VendorProfile::factory()->approved()->create();
    Service::factory()->sale()->published()->count(16)->create(['vendor_profile_id' => $vendor->id]);
    Service::factory()->rental()->published()->create(['vendor_profile_id' => $vendor->id]);
    Service::factory()->sale()->published()->create();
    $url = "/{$locale}/vendors/{$vendor->slug}";
    $response = $this->get($url.'?type=sale')->assertOk();
    $page = $response->viewData('services');
    expect(collect($page->items())->every(fn ($service) => $service->vendor_profile_id === $vendor->id && $service->product_type->value === 'sale'))->toBeTrue();
    $response->assertSee('data-vendor-service-filter', false);
    $response->assertSee('aria-current="page"', false)->assertDontSee('id="vendor-service-type"', false)
        ->assertDontSee('data-vendor-types-count', false);
    expect($page->nextPageUrl())->toContain('type=sale');
    expect($this->get($page->nextPageUrl())->assertOk()->viewData('services')->count())->toBe(1);
    expect($this->get($url)->assertOk()->viewData('services')->count())->toBe(15);
    $this->getJson($url.'?type=invalid')->assertUnprocessable();
})->with(['en', 'ar']);

it('presents unique localized service names governorates and an uncounted rental badge', function (string $locale): void {
    $primary = Governorate::factory()->create(['name' => ['en' => 'Primary Province', 'ar' => 'المحافظة الأساسية']]);
    $coverage = Governorate::factory()->create(['name' => ['en' => 'Coverage Province', 'ar' => 'محافظة التغطية']]);
    $city = City::factory()->create(['governorate_id' => $coverage->id, 'name' => ['en' => 'Hidden City', 'ar' => 'المدينة المخفية']]);
    $vendor = VendorProfile::factory()->approved()->create(['primary_governorate_id' => $primary->id]);
    $vendor->coverageAreas()->create(['city_id' => $city->id]);
    Service::factory()->rental()->published()->count(2)->create(['vendor_profile_id' => $vendor->id, 'name' => ['en' => 'Real Rental Name', 'ar' => 'اسم خدمة التأجير']]);
    Service::factory()->rental()->create(['vendor_profile_id' => $vendor->id, 'name' => ['en' => 'Private Draft', 'ar' => 'مسودة خاصة']]);
    $response = $this->get("/{$locale}/vendors/{$vendor->slug}")->assertOk();
    expect($response->viewData('serviceNames')->all())->toBe([$locale === 'ar' ? 'اسم خدمة التأجير' : 'Real Rental Name']);
    $response->assertDontSee('data-vendor-services-count', false)->assertDontSee('Private Draft');
    $response->assertSee($locale === 'ar' ? 'المحافظة الأساسية' : 'Primary Province')->assertSee($locale === 'ar' ? 'محافظة التغطية' : 'Coverage Province');
    preg_match('/<li data-vendor-service-type="rental"[^>]*>(.*?)<\/li>/s', $response->getContent(), $badge);
    expect(strip_tags($badge[1] ?? ''))->not->toMatch('/\d/');
    $api = $this->getJson('/api/v1/customer/vendors/'.$vendor->public_id, ['Accept-Language' => $locale])->assertOk();
    expect(collect($api->json('data.governorates'))->pluck('name')->all())->toBe([$locale === 'ar' ? 'المحافظة الأساسية' : 'Primary Province', $locale === 'ar' ? 'محافظة التغطية' : 'Coverage Province']);
    foreach (['home.featured-vendors', 'storefront.vendor-card'] as $component) {
        $html = Blade::render('<x-'.$component.' :vendors="$vendors" :vendor="$vendor" />', ['vendors' => collect([$vendor->loadCount('services')]), 'vendor' => $vendor]);
        expect($html)->toContain($locale === 'ar' ? 'محافظة التغطية' : 'Coverage Province');
    }
    $service = $vendor->services()->displayable()->firstOrFail();
    $html = Blade::render('<x-home.featured-services :services="$services" :block="$block" />', ['services' => collect([$service]), 'block' => ['public_id' => 'qa-services', 'payload' => []]]);
    expect($html)->toContain($locale === 'ar' ? 'محافظة التغطية' : 'Coverage Province');
})->with(['en', 'ar']);

it('combines existing portfolio and published service media and falls back from missing conversions', function (): void {
    Storage::fake('public');
    $vendor = VendorProfile::factory()->approved()->create();
    $service = Service::factory()->rental()->published()->create(['vendor_profile_id' => $vendor->id]);
    $media = function ($owner, string $collection, string $name) {
        $item = $owner->media()->create(['collection_name' => $collection, 'name' => $name, 'file_name' => $name.'.png', 'mime_type' => 'image/png', 'disk' => 'public', 'size' => 100, 'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => ['large' => true], 'responsive_images' => []]);
        Storage::disk('public')->put($item->getPathRelativeToRoot(), 'fixture');

        return $item;
    };
    $own = $media($vendor, 'portfolio', 'own');
    $offering = $media($service, 'gallery', 'offering');
    $missing = $media($vendor, 'portfolio', 'missing');
    Storage::disk('public')->delete($missing->getPathRelativeToRoot());
    $response = $this->get('/en/vendors/'.$vendor->slug)->assertOk();
    expect($response->viewData('galleryItems')->pluck('url')->all())->toBe([$own->getUrl(), $offering->getUrl()]);
    $api = $this->getJson('/api/v1/customer/vendors/'.$vendor->public_id.'/portfolio')->assertOk();
    expect(collect($api->json('data.items'))->pluck('url')->all())->toBe([$own->getUrl(), $offering->getUrl()]);
    for ($index = 0; $index < 25; $index++) {
        $media($vendor, 'portfolio', 'extra-'.$index);
    }
    expect($this->get('/en/vendors/'.$vendor->slug)->assertOk()->viewData('galleryItems')->count())->toBe(27);
});

it('renders coverage governorates for a recommendation collection without extra relation queries', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    foreach (City::factory()->count(5)->create() as $city) {
        $vendor->coverageAreas()->create(['city_id' => $city->id]);
    }
    Service::factory()->rental()->published()->count(6)->create(['vendor_profile_id' => $vendor->id]);
    $services = app(ServiceRecommendationRepository::class)->recommend(null, null, null);
    DB::enableQueryLog();
    $before = count(DB::getQueryLog());
    foreach ($services as $service) {
        expect(app(StorefrontText::class)->vendorGovernorates($service->vendor)->count())->toBe(6);
    }
    expect(count(DB::getQueryLog()))->toBe($before);
    DB::disableQueryLog();
});

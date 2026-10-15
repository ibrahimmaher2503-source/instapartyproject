<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

it('keeps vendor rail links, uploaded covers and real ratings in both languages', function (string $locale): void {
    app()->setLocale($locale);
    URL::defaults(['locale' => $locale]);
    Storage::fake('public');
    $vendors = collect(range(1, 5))->map(function (int $index): VendorProfile {
        $vendor = (new VendorProfile)->forceFill([
            'business_name' => ['en' => 'Vendor '.$index, 'ar' => 'بائع '.$index],
            'slug' => 'vendor-'.$index, 'public_id' => 'vendor-'.$index,
            'cover_path' => 'images/uploaded-vendor-'.$index.'.jpg',
            'rating_avg' => $index === 1 ? '4.2' : '0.0',
            'rating_count' => $index === 1 ? 7 : 0,
        ]);
        $vendor->services_count = 3;
        Storage::disk('public')->put($vendor->cover_path, 'uploaded cover');
        $vendor->setRelation('primaryGovernorate', null)->setRelation('primaryCity', null)
            ->setRelation('coverageAreas', collect());

        return $vendor;
    });
    $html = Blade::render('<x-home.featured-vendors :vendors="$vendors" />', compact('vendors'));

    expect($html)->toContain('data-home-vendors', 'data-vendor-pages', '4.2', '(7)')
        ->not->toContain('data-vendor-step', '(0)', '4.9');
    foreach ($vendors as $vendor) {
        expect($html)->toContain('/'.$locale.'/vendors/'.$vendor->slug, $vendor->cover_path);
    }
})->with(['en', 'ar']);

it('renders all CMS service pages with real category filters and review counts', function (string $locale): void {
    app()->setLocale($locale);
    $category = Category::factory()->create([
        'code' => 'cakes',
        'name' => ['en' => 'Cakes', 'ar' => 'كيك'],
    ]);
    $services = collect(['rental', 'sale', 'digital', 'rental', 'sale', 'digital', 'rental', 'sale', 'digital'])->map(
        fn (string $type, int $index) => Service::factory()->published()->create([
            'category_id' => $category->id,
            'product_type' => $type,
            'name' => ['en' => 'Service '.$index, 'ar' => 'خدمة '.$index],
            'rating_avg' => $index === 0 ? '4.8' : '0.0',
            'rating_count' => $index === 0 ? 12 : 0,
        ]),
    );
    $services->first()->media()->create([
        'collection_name' => 'gallery', 'name' => 'Demo photo 1',
        'file_name' => $services->first()->slug.'-photo-1.jpg', 'mime_type' => 'image/jpeg',
        'disk' => 'public', 'size' => 100, 'manipulations' => [],
        'custom_properties' => [], 'generated_conversions' => [], 'responsive_images' => [],
    ]);
    $html = Blade::render('<x-home.featured-services :block="$block" :services="$services" />', [
        'block' => ['public_id' => 'reference-services', 'payload' => ['title' => 'CMS title']],
        'services' => $services,
    ]);

    expect($html)->toContain('CMS title', 'data-service-page="1"', 'data-service-filter="'.$category->public_id.'"')
        ->toContain(__('storefront.home.featured.reviews', ['count' => 12]))
        ->not->toContain('storefront.home.featured.', '(0 reviews)');
    expect(substr_count($html, 'data-reference-photo'))->toBe(1);
    foreach ($services as $service) {
        expect($html)->toContain('/'.$locale.'/services/'.$service->public_id);
    }
})->with(['en', 'ar']);

it('keeps uploaded category photos and limits reference photography to placeholders', function (): void {
    $categories = collect([
        Category::factory()->create(['code' => 'cakes', 'icon_path' => 'images/cms-cake.jpg']),
        Category::factory()->create(['code' => 'venues', 'icon_path' => 'https://picsum.photos/demo']),
    ]);
    $html = Blade::render('<x-home.taxonomy :block="$block" :items="$items" kind="category" />', [
        'block' => ['public_id' => 'reference-categories', 'payload' => []],
        'items' => $categories,
    ]);

    expect($html)->toContain('images/cms-cake.jpg', 'sf-category-photo')
        ->toMatch('/data-category-code="cakes"\s+aria-hidden/')
        ->toMatch('/data-category-code="venues"\s+data-reference-photo/');
});

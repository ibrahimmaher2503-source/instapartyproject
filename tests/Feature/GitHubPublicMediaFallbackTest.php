<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Application\Services\PublicMediaUrl;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;

it('uses existing originals when conversions are missing and avoids URLs for absent media', function (): void {
    Storage::fake('public');
    Storage::fake('media-conversions');
    $service = Service::factory()->published()->create();
    $media = $service->media()->create([
        'collection_name' => 'gallery',
        'name' => 'public-image',
        'file_name' => 'public-image.png',
        'mime_type' => 'image/png',
        'disk' => 'public',
        'conversions_disk' => 'media-conversions',
        'size' => 100,
        'manipulations' => [],
        'custom_properties' => [],
        'generated_conversions' => ['medium' => true],
        'responsive_images' => [],
    ]);
    $urls = app(PublicMediaUrl::class);
    expect($urls->for($media, 'medium'))->toBeNull();

    Storage::disk('public')->put($media->getPathRelativeToRoot(), 'original fixture');
    expect($urls->for($media, 'medium'))->toBe($media->getUrl());

    Storage::disk('media-conversions')->put($media->getPathRelativeToRoot('medium'), 'conversion fixture');
    expect($urls->for($media, 'medium'))->toBe($media->getUrl('medium'));
});

it('renders homepage and listing fallbacks instead of missing service images and vendor logos', function (string $locale): void {
    app()->setLocale($locale);
    Storage::fake('public');
    $vendor = VendorProfile::factory()->approved()->create([
        'logo_path' => 'vendor-logos/missing.png',
        'cover_path' => 'vendor-covers/missing.png',
    ]);
    $service = Service::factory()->published()->create(['vendor_profile_id' => $vendor->id]);
    $media = $service->media()->create([
        'collection_name' => 'gallery', 'name' => 'missing-service-image',
        'file_name' => 'missing-service-image.png', 'mime_type' => 'image/png',
        'disk' => 'public', 'conversions_disk' => 'public', 'size' => 100, 'manipulations' => [],
        'custom_properties' => [], 'generated_conversions' => [], 'responsive_images' => [],
    ]);
    $block = ['public_id' => 'github-media-regression', 'payload' => []];

    $homepage = Blade::render('<x-home.featured-services :block="$block" :services="$services" /><x-home.featured-vendors :vendors="$vendors" />', [
        'block' => $block, 'services' => [$service], 'vendors' => [$vendor],
    ]);
    $listing = Blade::render('<x-storefront.service-card :service="$service" /><x-storefront.vendor-card :vendor="$vendor" />', [
        'service' => $service, 'vendor' => $vendor,
    ]);

    foreach ([$homepage, $listing] as $html) {
        expect($html)->not->toContain($media->getUrl(), $media->getUrl('medium'))
            ->not->toContain(Storage::disk('public')->url($vendor->logo_path), Storage::disk('public')->url($vendor->cover_path));
    }

    $this->get("/{$locale}/vendors/{$vendor->slug}")->assertOk()
        ->assertViewHas('logoUrl', null)->assertViewHas('coverImageUrl', null);
    Storage::disk('public')->put($vendor->logo_path, 'logo fixture');
    expect(app(PublicMediaUrl::class)->publicPath($vendor->logo_path))->toBe(Storage::disk('public')->url($vendor->logo_path));
    $this->get("/{$locale}/vendors/{$vendor->slug}")->assertOk()
        ->assertViewHas('logoUrl', Storage::disk('public')->url($vendor->logo_path));
})->with(['en', 'ar']);

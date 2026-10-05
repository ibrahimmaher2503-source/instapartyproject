<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

function publicGalleryFixture(VendorProfile $vendor, string $name): Media
{
    return $vendor->media()->create([
        'collection_name' => 'portfolio',
        'name' => $name,
        'file_name' => $name.'.png',
        'mime_type' => 'image/png',
        'disk' => 'public',
        'size' => 100,
        'manipulations' => [],
        'custom_properties' => [],
        'generated_conversions' => [],
        'responsive_images' => [],
    ]);
}

it('renders only the active vendors portfolio images and hides empty gallery controls', function (string $locale): void {
    Storage::fake('public');
    $vendor = VendorProfile::factory()->approved()->create();
    $other = VendorProfile::factory()->approved()->create();
    $first = publicGalleryFixture($vendor, 'active-first');
    $second = publicGalleryFixture($vendor, 'active-second');
    $foreign = publicGalleryFixture($other, 'foreign-image');

    $response = $this->get("/{$locale}/vendors/{$vendor->slug}")->assertOk();
    expect($response->viewData('galleryItems')->pluck('url')->all())->toBe([$first->getUrl(), $second->getUrl()]);
    $response->assertSee('data-vendor-gallery', false)
        ->assertSee('data-gallery-step="1"', false)
        ->assertDontSee($foreign->getUrl(), false);

    $second->delete();
    $this->get("/{$locale}/vendors/{$vendor->slug}")->assertOk()
        ->assertSee('data-vendor-gallery', false)->assertDontSee('data-gallery-step', false);
    $first->delete();
    $this->get("/{$locale}/vendors/{$vendor->slug}")->assertOk()->assertDontSee('data-vendor-gallery', false);
})->with(['en', 'ar']);

<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Contracts\PublicVendorPortfolioReader;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

function publicGalleryFixture(
    VendorProfile $vendor,
    string $name,
    string $disk = 's3-public',
    bool $writeFile = true,
    ?string $conversionsDisk = null,
    bool $generatedConversions = false,
): Media {
    $media = $vendor->media()->create([
        'collection_name' => 'portfolio',
        'name' => $name,
        'file_name' => $name.'.png',
        'mime_type' => 'image/png',
        'disk' => $disk,
        'conversions_disk' => $conversionsDisk,
        'size' => 100,
        'manipulations' => [],
        'custom_properties' => [],
        'generated_conversions' => $generatedConversions ? ['large' => true, 'thumb' => true] : [],
        'responsive_images' => [],
    ]);
    if ($writeFile) {
        Storage::disk($disk)->put($media->getPathRelativeToRoot(), 'public gallery fixture');
    }
    if ($generatedConversions && $conversionsDisk !== null) {
        Storage::disk($conversionsDisk)->put($media->getPathRelativeToRoot('large'), 'private large conversion');
        Storage::disk($conversionsDisk)->put($media->getPathRelativeToRoot('thumb'), 'private thumb conversion');
    }

    return $media;
}

it('renders only the active vendors portfolio images and hides empty gallery controls', function (string $locale): void {
    Storage::fake('s3-public');
    Storage::fake('local');
    $vendor = VendorProfile::factory()->approved()->create();
    $other = VendorProfile::factory()->approved()->create();
    $first = publicGalleryFixture($vendor, 'active-first');
    $second = publicGalleryFixture($vendor, 'active-second');
    $foreign = publicGalleryFixture($other, 'foreign-image');
    $private = publicGalleryFixture($vendor, 'private-image', 'local');
    $missing = publicGalleryFixture($vendor, 'missing-image', 's3-public', false);
    $privateConversion = publicGalleryFixture($vendor, 'private-conversion-image', 's3-public', true, 'local', true);

    $response = $this->get("/{$locale}/vendors/{$vendor->slug}")->assertOk();
    expect($response->viewData('galleryItems')->pluck('url')->all())->toBe([$first->getUrl(), $second->getUrl()]);
    $response->assertSee('data-vendor-gallery', false)
        ->assertSee('data-gallery-step="1"', false)
        ->assertDontSee($foreign->getUrl(), false)
        ->assertDontSee($private->getUrl(), false)
        ->assertDontSee($missing->getUrl(), false)
        ->assertDontSee($privateConversion->getUrl('large'), false);

    $second->delete();
    $this->get("/{$locale}/vendors/{$vendor->slug}")->assertOk()
        ->assertSee('data-vendor-gallery', false)->assertDontSee('data-gallery-step', false);
    $first->delete();
    $this->get("/{$locale}/vendors/{$vendor->slug}")->assertOk()->assertDontSee('data-vendor-gallery data-service-gallery', false)->assertSee('data-vendor-gallery-empty', false);
})->with(['en', 'ar']);

it('shows only the approved vendors own existing public portfolio on service detail in both locales', function (string $locale): void {
    Storage::fake('s3-public');
    Storage::fake('local');
    $vendor = VendorProfile::factory()->approved()->create();
    $other = VendorProfile::factory()->approved()->create();
    $service = Service::factory()->sale()->published()->create([
        'vendor_profile_id' => $vendor->id,
    ]);
    $ownerImage = publicGalleryFixture($vendor, 'service-owner-image');
    $foreignImage = publicGalleryFixture($other, 'service-foreign-image');
    $privateImage = publicGalleryFixture($vendor, 'service-private-image', 'local');
    $missingImage = publicGalleryFixture($vendor, 'service-missing-image', 's3-public', false);

    $response = $this->get(route('storefront.services.show', [
        'locale' => $locale,
        'servicePublicId' => $service->public_id,
    ]))->assertOk();

    expect($response->viewData('vendorPortfolioItems'))->toHaveCount(1)
        ->and($response->viewData('vendorPortfolioItems')[0]['url'])->toBe($ownerImage->getUrl());
    $response->assertSee('data-vendor-portfolio', false)
        ->assertSee(__('storefront.vendor.portfolio_heading', [], $locale))
        ->assertSee($ownerImage->getUrl(), false)
        ->assertDontSee($foreignImage->getUrl(), false)
        ->assertDontSee($privateImage->getUrl(), false)
        ->assertDontSee($missingImage->getUrl(), false)
        ->assertSee('data-service-gallery', false);
})->with(['en', 'ar']);

it('does not return portfolio media for an unapproved vendor', function (): void {
    Storage::fake('s3-public');
    $vendor = VendorProfile::factory()->create();
    publicGalleryFixture($vendor, 'pending-owner-image');

    expect(app(PublicVendorPortfolioReader::class)
        ->forApprovedVendor($vendor->public_id))->toBe([]);
});

it('lets a vendor upload, list, and delete only their own approved public portfolio media', function (): void {
    Storage::fake('s3-public');
    $vendor = VendorProfile::factory()->approved()->create();
    $other = VendorProfile::factory()->approved()->create();
    Sanctum::actingAs($vendor->user);

    $upload = $this->postJson('/api/v1/vendor/profile/portfolio', [
        'files' => [UploadedFile::fake()->image('portfolio.png', 800, 600)],
    ])->assertCreated();

    $mediaPublicId = $upload->json('data.0.public_id');
    expect($mediaPublicId)->toBeString()->not->toBeEmpty();

    $this->getJson('/api/v1/vendor/profile/portfolio')
        ->assertOk()
        ->assertJsonPath('data.0.public_id', $mediaPublicId);

    $media = $vendor->getMedia('portfolio')->sole();
    $publicProfile = $this->get('/en/vendors/'.$vendor->slug)->assertOk();
    expect($publicProfile->viewData('galleryItems')->all())->toBe([[
        'url' => $media->getUrl('large'),
        'thumb' => $media->getUrl('thumb'),
        'service_public_id' => null,
    ]]);

    Sanctum::actingAs($other->user);
    $this->deleteJson('/api/v1/vendor/profile/portfolio/'.$mediaPublicId)->assertNotFound();
    expect($vendor->getMedia('portfolio'))->toHaveCount(1);

    Sanctum::actingAs($vendor->user);
    $this->deleteJson('/api/v1/vendor/profile/portfolio/'.$mediaPublicId)->assertOk();
    Sanctum::actingAs($vendor->user->fresh());
    $this->getJson('/api/v1/vendor/profile/portfolio')->assertOk()->assertJsonPath('data', []);
    $this->get('/en/vendors/'.$vendor->slug)->assertOk()->assertDontSee('data-vendor-gallery data-service-gallery', false);
    Storage::disk('s3-public')->assertMissing($media->getPathRelativeToRoot());
});

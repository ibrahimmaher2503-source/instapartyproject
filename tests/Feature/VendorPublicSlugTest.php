<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\RegisterVendorAction;
use App\Modules\Identity\Application\Actions\UpdateVendorProfileAction;
use App\Modules\Identity\Application\DTOs\RegisterVendorDTO;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

it('preserves unique bounded public slugs for duplicate business names and redirects old links after a rename', function (string $locale, string $businessName): void {
    Mail::fake();
    Queue::fake();
    Role::findOrCreate('vendor', 'web');
    $governorate = Governorate::factory()->create();
    $profiles = [];
    foreach ([1, 2] as $number) {
        $profiles[] = app(RegisterVendorAction::class)->execute(RegisterVendorDTO::fromArray([
            'name' => 'Shared Vendor Name',
            'email' => "duplicate-slug-{$number}@example.test",
            'phone_e164' => "+20100000100{$number}",
            'password' => 'SafePassword123!',
            'password_confirmation' => 'SafePassword123!',
            'business_name' => ['en' => $businessName, 'ar' => 'فعاليات مشتركة'],
            'business_type' => 'individual',
            'primary_governorate_id' => $governorate->id,
        ]));
    }
    [$vendor, $other] = $profiles;
    $slug = $vendor->slug;
    $publicId = $vendor->public_id;
    expect($slug)->toMatch('/^[a-z0-9-]+-[a-z0-9]{10}$/')->not->toBe($other->slug)
        ->and(mb_strlen($slug))->toBeLessThanOrEqual(160);
    $vendor->update(['approval_status' => 'approved']);
    $this->actingAs($vendor->user);
    app(UpdateVendorProfileAction::class)->execute($vendor, [
        'business_name' => ['en' => 'Renamed Events', 'ar' => 'فعاليات بالاسم الجديد'],
        'slug' => 'attempted-new-slug',
    ]);
    expect($vendor->refresh()->slug)->toBe($slug)
        ->and($vendor->public_id)->toBe($publicId)
        ->and($vendor->getRouteKeyName())->toBe('public_id');

    $this->get("/{$locale}/vendors/{$publicId}?source=old-link")
        ->assertStatus(301)->assertRedirect(url("/{$locale}/vendors/{$slug}?source=old-link"));
    $this->get("/{$locale}/vendors/{$slug}")->assertOk()
        ->assertSeeText($locale === 'ar' ? 'فعاليات بالاسم الجديد' : 'Renamed Events');
    $this->get("/{$locale}/vendors?q=Renamed")
        ->assertOk()->assertSee("/{$locale}/vendors/{$slug}", false);
})->with(['en', 'ar'])->with(['Shared Events', str_repeat('Long ', 48)]);

it('does not expose an unapproved or missing vendor through either identifier', function (string $locale): void {
    $vendor = VendorProfile::factory()->pending()->create();
    $this->get("/{$locale}/vendors/{$vendor->slug}")->assertNotFound();
    $this->get("/{$locale}/vendors/{$vendor->public_id}")->assertNotFound();
    $this->get("/{$locale}/vendors/unknown-vendor-slug")->assertNotFound();
})->with(['en', 'ar']);

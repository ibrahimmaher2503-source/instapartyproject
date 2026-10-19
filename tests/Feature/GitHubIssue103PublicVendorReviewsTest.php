<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Domain\Models\VendorReview;

it('shows only approved vendor review text and rating on public storefront views', function (string $locale): void {
    $vendor = VendorProfile::factory()->approved()->create([
        // Public rendering must reflect the approved review even when the
        // denormalized vendor profile summary has not caught up yet.
        'rating_avg' => 0,
        'rating_count' => 0,
    ]);

    $createReview = function (string $body, string $status, int $rating) use ($vendor): VendorReview {
        $bookingVendor = BookingVendor::factory()->create(['vendor_profile_id' => $vendor->id]);
        $factory = match ($status) {
            'approved' => VendorReview::factory()->approved(),
            'pending' => VendorReview::factory()->pending(),
            'rejected' => VendorReview::factory()->rejected(),
            'hidden' => VendorReview::factory()->hidden(),
        };

        return $factory->create([
            'vendor_profile_id' => $vendor->id,
            'booking_vendor_id' => $bookingVendor->id,
            'body' => $body,
            'rating' => $rating,
        ]);
    };

    $approved = $createReview('The setup was excellent and on time.', 'approved', 4);
    $privateBodies = [
        'PENDING REVIEW MUST STAY PRIVATE',
        'REJECTED REVIEW MUST STAY PRIVATE',
        'HIDDEN REVIEW MUST STAY PRIVATE',
        'A DIFFERENT VENDOR REVIEW MUST STAY PRIVATE',
    ];
    $createReview($privateBodies[0], 'pending', 5);
    $createReview($privateBodies[1], 'rejected', 5);
    $createReview($privateBodies[2], 'hidden', 5);

    $otherVendor = VendorProfile::factory()->pending()->create();
    $otherBookingVendor = BookingVendor::factory()->create(['vendor_profile_id' => $otherVendor->id]);
    VendorReview::factory()->approved()->create([
        'vendor_profile_id' => $otherVendor->id,
        'booking_vendor_id' => $otherBookingVendor->id,
        'body' => $privateBodies[3],
        'rating' => 5,
    ]);

    foreach (["/{$locale}/vendors/{$vendor->slug}", "/{$locale}/vendors"] as $url) {
        $response = $this->get($url)->assertOk()
            ->assertSeeText($approved->body)
            ->assertSee('4 / 5')
            ->assertSee('4.0')
            ->assertDontSee(__('storefront.service.no_reviews'));

        foreach ($privateBodies as $privateBody) {
            $response->assertDontSee($privateBody);
        }
    }

    $profile = $this->getJson('/api/v1/customer/vendors/'.$vendor->public_id)->assertOk();
    expect((float) $profile->json('data.rating_avg'))->toBe(4.0)
        ->and($profile->json('data.rating_count'))->toBe(1)
        ->and($profile->json('data.featured_review.rating'))->toBe(4)
        ->and($profile->json('data.featured_review.body'))->toBe($approved->body)
        ->and($profile->json('data.featured_review.reviewer_first_name'))->toBe(strtok($approved->reviewer->name, ' '))
        ->and($profile->json('data.featured_review.created_at'))->toBe($approved->getRawOriginal('created_at'));

    $catalogIndex = $this->getJson('/api/v1/customer/vendors?per_page=24')->assertOk();
    $indexedVendor = collect($catalogIndex->json('data'))->firstWhere('public_id', $vendor->public_id);
    expect($indexedVendor)->not->toBeNull()
        ->and((float) $indexedVendor['rating_avg'])->toBe(4.0)
        ->and($indexedVendor['rating_count'])->toBe(1);

    $reviewList = $this->getJson('/api/v1/public/vendors/'.$vendor->public_id.'/reviews')->assertOk();
    expect(collect($reviewList->json('data'))->pluck('body')->all())->toBe([$approved->body])
        ->and($reviewList->json('data.0'))->not->toHaveKeys(['user_id', 'email']);
})->with(['en', 'ar']);

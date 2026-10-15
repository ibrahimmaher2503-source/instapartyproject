<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Filament\Vendor\Pages\VendorReviewsPage;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('shows the response label and moderation state and allows submitted responses to be edited', function (string $locale): void {
    app()->setLocale($locale);

    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $service = Service::factory()->published()->create(['vendor_profile_id' => $vendor->id]);
    $review = ServiceReview::factory()->approved()->create(['service_id' => $service->id]);

    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($user, 'vendor');

    $component = Livewire::test(VendorReviewsPage::class)
        ->callTableAction('respond', $review, data: ['body' => 'Thank you for your feedback.'])
        ->assertTableActionHasLabel('respond', __('reviews::reviews.actions.edit_response'), $review->fresh())
        ->assertSee(__('reviews::reviews.response.status'))
        ->assertSee(__('reviews.moderation_status.pending'))
        ->mountTableAction('respond', $review)
        ->assertSee(__('reviews::reviews.response.body'))
        ->assertTableActionDataSet(['body' => 'Thank you for your feedback.'])
        ->callTableAction('respond', $review, data: ['body' => 'We appreciate your feedback.']);

    $response = ReviewResponse::query()
        ->where('review_type', 'service')
        ->where('review_id', $review->id)
        ->where('vendor_profile_id', $vendor->id)
        ->firstOrFail();

    expect($response->body)->toBe('We appreciate your feedback.')
        ->and($response->moderation_status)->toBe(ModerationStatus::Pending)
        ->and(ReviewResponse::query()->where('review_id', $review->id)->count())->toBe(1);

    $component->assertSee(__('reviews.moderation_status.pending'))
        ->assertTableActionHasLabel('respond', __('reviews::reviews.actions.edit_response'), $review->fresh());
})->with(['en', 'ar']);

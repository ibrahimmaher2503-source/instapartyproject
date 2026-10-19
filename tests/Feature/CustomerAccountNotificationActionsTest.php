<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

function createAccountNotification(User $user, array $data): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'customer-account-test',
        'data' => $data,
    ]);
}

it('renders localized notifications and only regenerated links to the customer’s own bookings', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $otherCustomer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->create(['customer_id' => $customer->id]);
    $otherBooking = Booking::factory()->create(['customer_id' => $otherCustomer->id]);
    $ownedPath = route('storefront.account.bookings.show', ['locale' => 'en', 'publicId' => $booking->public_id], false);
    $otherPath = route('storefront.account.bookings.show', ['locale' => 'en', 'publicId' => $otherBooking->public_id], false);

    createAccountNotification($customer, [
        'title' => 'Legacy title',
        'body' => 'Legacy body',
        'localized_content' => [
            'en' => ['title' => 'English title', 'body' => 'English body'],
            'ar' => ['title' => 'عنوان عربي', 'body' => 'نص عربي'],
        ],
        'action_url' => 'https://untrusted.example'.$ownedPath,
    ]);
    createAccountNotification($customer, [
        'title' => 'Foreign booking notice',
        'action_url' => $otherPath,
    ]);
    createAccountNotification($customer, [
        'title' => 'Arbitrary link notice',
        'action_url' => 'https://untrusted.example/collect',
    ]);

    $response = $this->actingAs($customer)->get(route('storefront.account.notifications', ['locale' => $locale]))
        ->assertOk()
        ->assertSeeText($locale === 'ar' ? 'عنوان عربي' : 'English title')
        ->assertSee('name="_token"', false)
        ->assertSee('name="_method" value="PATCH"', false)
        ->assertDontSee('untrusted.example')
        ->assertDontSee($otherBooking->public_id);

    $response->assertSee(route('storefront.account.bookings.show', [
        'locale' => $locale,
        'publicId' => $booking->public_id,
    ]), false);
})->with(['en', 'ar']);

it('lets a customer mark only their own notifications read and unread', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $otherCustomer = User::factory()->asCustomer()->create();
    $notification = createAccountNotification($customer, ['title' => 'Owned']);
    $foreignNotification = createAccountNotification($otherCustomer, ['title' => 'Foreign']);

    $this->actingAs($customer)
        ->patch(route('storefront.account.notifications.read', ['locale' => 'en', 'notificationId' => $notification->id]))
        ->assertRedirect(route('storefront.account.notifications', ['locale' => 'en']));
    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->patch(route('storefront.account.notifications.unread', ['locale' => 'en', 'notificationId' => $notification->id]))
        ->assertRedirect(route('storefront.account.notifications', ['locale' => 'en']));
    expect($notification->fresh()->read_at)->toBeNull();

    $this->patch(route('storefront.account.notifications.read', ['locale' => 'en', 'notificationId' => $foreignNotification->id]))
        ->assertNotFound();
    expect($foreignNotification->fresh()->read_at)->toBeNull();
});

it('keeps notification state actions customer-only', function (): void {
    $vendor = User::factory()->asVendor()->create();
    $notification = createAccountNotification($vendor, ['title' => 'Vendor notice']);

    $this->actingAs($vendor)
        ->patch(route('storefront.account.notifications.read', ['locale' => 'en', 'notificationId' => $notification->id]))
        ->assertForbidden();
});

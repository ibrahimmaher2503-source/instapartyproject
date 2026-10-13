<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Support\CarbonImmutable;

it('uses each account heading as the localized document title', function (string $locale): void {
    $user = User::factory()->asCustomer()->create(['timezone' => 'UTC']);
    $booking = Booking::factory()->create(['customer_id' => $user->id]);
    $this->actingAs($user);

    $paths = [
        "/{$locale}/account",
        "/{$locale}/account/bookings",
        "/{$locale}/account/bookings/{$booking->public_id}",
        "/{$locale}/account/payments",
        "/{$locale}/account/plans",
        "/{$locale}/account/favorites",
        "/{$locale}/account/notifications",
        "/{$locale}/account/reviews",
        "/{$locale}/account/profile",
        "/{$locale}/account/security",
    ];

    foreach ($paths as $path) {
        $html = $this->get($path)->assertOk()->getContent();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($document);
        $title = trim($xpath->evaluate('string(//title)'));
        $heading = trim($xpath->evaluate('string(//header[contains(@class,"sf-account-intro")]/h1)'));

        expect($heading)->not->toBeEmpty()
            ->and($title)->toBe($heading.' · '.trans('storefront.common.site_name', [], $locale));
    }
})->with(['en', 'ar']);

it('does not double-escape the customer name in the account heading or title', function (): void {
    $name = 'A&B <Party>';
    $user = User::factory()->asCustomer()->create(['name' => $name]);
    $html = $this->actingAs($user)->get('/en/account')->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $title = trim($xpath->evaluate('string(//title)'));
    $heading = trim($xpath->evaluate('string(//header[contains(@class,"sf-account-intro")]/h1)'));

    expect($heading)->toBe('Hello, '.$name)
        ->and($title)->toBe('Hello, '.$name.' · '.trans('storefront.common.site_name', [], 'en'))
        ->and($heading)->not->toContain('&amp;', '&lt;', '&gt;')
        ->and($title)->not->toContain('&amp;', '&lt;', '&gt;');
});

it('formats account date-times by locale and keeps the viewer timezone visible', function (string $locale): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $date = CarbonImmutable::parse('2026-11-20 15:00:00', 'UTC');
    $user = User::factory()->asCustomer()->create(['timezone' => 'UTC']);
    $booking = Booking::factory()->create([
        'customer_id' => $user->id,
        'event_starts_at' => $date,
        'event_ends_at' => $date->addHours(2),
    ]);
    Payment::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => $user->id,
        'created_at' => $date,
    ]);
    NotificationDispatch::factory()->create([
        'user_id' => $user->id,
        'channel' => NotificationChannel::InApp,
        'context' => ['subject' => 'Date notice', 'body' => 'Date notice body'],
        'created_at' => $date,
    ]);
    $this->actingAs($user);

    $separator = $locale === 'ar' ? '،' : ',';
    $wrongSeparator = $locale === 'ar' ? ',' : '،';
    $dateText = $date->locale($locale)->translatedFormat('d F Y').$separator.' 15:00';
    $wrongDateText = $date->locale($locale)->translatedFormat('d F Y').$wrongSeparator.' 15:00';

    foreach ([
        "/{$locale}/account",
        "/{$locale}/account/bookings",
        "/{$locale}/account/bookings/{$booking->public_id}",
        "/{$locale}/account/payments",
        "/{$locale}/account/notifications",
    ] as $path) {
        $response = $this->get($path)->assertOk()->assertSeeText($dateText)->assertDontSeeText($wrongDateText);
        if (in_array($path, [
            "/{$locale}/account",
            "/{$locale}/account/bookings",
            "/{$locale}/account/bookings/{$booking->public_id}",
        ], true)) {
            $response->assertSeeText('UTC');
        }
    }
})->with(['en', 'ar']);

it('sets the DOB date input maximum to today and rejects future dates', function (): void {
    $user = User::factory()->asCustomer()->create();
    $this->actingAs($user);

    $this->get('/en/account/profile')
        ->assertOk()
        ->assertSee('type="date" name="date_of_birth" max="'.today()->toDateString().'"', false);

    $this->put('/en/account/profile', ['date_of_birth' => today()->addDay()->toDateString()])
        ->assertSessionHasErrors('date_of_birth');
});

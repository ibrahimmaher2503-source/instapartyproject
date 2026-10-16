<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;

/**
 * WCAG 2.x relative luminance for a #rgb or #rrggbb colour.
 */
function cancelButtonRelativeLuminance(string $hex): float
{
    $hex = ltrim($hex, '#');

    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }

    $channels = array_map(static function (string $pair): float {
        $value = hexdec($pair) / 255;

        return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, [substr($hex, 0, 2), substr($hex, 2, 2), substr($hex, 4, 2)]);

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * WCAG 2.x contrast ratio between two colours (1:1 to 21:1).
 */
function cancelButtonContrastRatio(string $foreground, string $background): float
{
    $a = cancelButtonRelativeLuminance($foreground);
    $b = cancelButtonRelativeLuminance($background);

    return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
}

it('renders the cancel booking control with a WCAG AA destructive style', function (string $locale): void {
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => 'vendor_review',
    ]);
    BookingVendor::factory()->create(['booking_id' => $booking->id, 'sub_status' => 'pending']);

    $html = $this->actingAs($customer)
        ->get("/{$locale}/account/bookings/{$booking->public_id}")
        ->assertOk()
        ->assertSee(__('account.cancel_booking', [], $locale))
        ->getContent();

    // The destructive control must carry the danger style class.
    expect($html)->toContain('sf-button--danger');

    $css = (string) file_get_contents(base_path('resources/css/storefront.css'));
    expect($css)->not->toBe('');

    preg_match('/\.sf-button--danger\s*\{([^}]*)\}/', $css, $rule);
    $declarations = $rule[1] ?? '';
    expect($declarations)->not->toBe('');

    preg_match('/background:\s*(#[0-9a-fA-F]{3,6})/', $declarations, $backgroundMatch);
    preg_match('/color:\s*(#[0-9a-fA-F]{3,6})/', $declarations, $colorMatch);

    $background = $backgroundMatch[1] ?? '';
    $color = $colorMatch[1] ?? '';
    expect($background)->not->toBe('')->and($color)->not->toBe('');

    // Retest criteria from #237: text/background contrast is at least 4.5:1.
    expect(cancelButtonContrastRatio($color, $background))->toBeGreaterThanOrEqual(4.5);
})->with(['en', 'ar']);

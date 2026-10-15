<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdjustment;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Shared\Domain\Models\CmsPage;
use App\Modules\Support\Domain\Models\FaqCategory;
use App\Modules\Support\Domain\Models\FaqItem;
use App\Modules\Support\Domain\Models\SupportTicket;
use Database\Seeders\RefundPolicyCmsPageSeeder;

it('lets an owning customer print their invoice without claiming an uncaptured payment', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->confirmed()->create([
        'customer_id' => $customer->id,
        'reference_no' => 'IP-2026-000731',
        'subtotal_minor' => 10_000,
        'delivery_total_minor' => 1_500,
        'discount_total_minor' => 500,
        'loyalty_redeemed_minor' => 250,
        'total_minor' => 10_750,
    ]);
    $service = Service::factory()->digital()->published()->create();
    $vendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $service->vendor_profile_id,
        'delivery_fee_minor' => 1_250,
        'delivery_fee_currency' => 'EGP',
    ]);
    BookingItem::factory()->digital()->create([
        'booking_vendor_id' => $vendor->id,
        'service_id' => $service->id,
        'line_total_minor' => 10_000,
        'line_total_currency' => 'EGP',
    ]);
    BookingAdjustment::query()->create([
        'booking_id' => $booking->id,
        'type' => 'transportation',
        'amount_minor' => 250,
        'currency' => 'EGP',
        'source' => 'Internal-only address correction note',
        'created_by_user_id' => $customer->id,
    ]);

    $response = $this->actingAs($customer, 'web')
        ->get(route('storefront.account.bookings.invoice', ['locale' => $locale, 'bookingPublicId' => $booking->public_id]))
        ->assertOk()
        ->assertSeeText(__('booking.invoice.title', [], $locale))
        ->assertSeeText('IP-2026-000731')
        ->assertSeeText(__('booking::booking.payment_status.unpaid', [], $locale))
        ->assertSeeText(__('booking.invoice.no_tax_details_recorded', [], $locale))
        ->assertSeeText(__('booking.invoice.tax_context', [], $locale))
        ->assertSeeText(__('booking.columns.transportation_adjustment', [], $locale))
        ->assertDontSee('Internal-only address correction note');

    expect($response->viewData('itemSubtotalMinor'))->toBe(10_000)
        ->and($response->viewData('vendorDeliveryMinor'))->toBe(1_250)
        ->and($response->viewData('transportAdjustmentMinor'))->toBe(250)
        ->and($response->viewData('subtotalReconciliationMinor'))->toBe(0)
        ->and($response->viewData('totalReconciliationMinor'))->toBe(0)
        ->and($response->viewData('paymentTotals')['net_paid_minor'])->toBe(0)
        ->and($response->viewData('paymentStatus'))->toBe('unpaid');

    $other = User::factory()->asCustomer()->create();
    $this->actingAs($other, 'web')
        ->get(route('storefront.account.bookings.invoice', ['locale' => $locale, 'bookingPublicId' => $booking->public_id]))
        ->assertNotFound();
})->with(['en', 'ar']);

it('shows an unresolved refund before the captured payment status on the customer invoice', function (string $locale, string $refundStatus): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->confirmed()->create([
        'customer_id' => $customer->id,
        'total_minor' => 10_000,
    ]);
    $payment = Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'user_id' => $customer->id,
        'amount_minor' => 10_000,
        'amount_currency' => $booking->total_currency,
    ]);
    Refund::factory()->create([
        'payment_id' => $payment->id,
        'booking_id' => $booking->id,
        'amount_minor' => 2_000,
        'amount_currency' => $booking->total_currency,
        'status' => $refundStatus,
    ]);

    $this->actingAs($customer, 'web')
        ->get(route('storefront.account.bookings.invoice', ['locale' => $locale, 'bookingPublicId' => $booking->public_id]))
        ->assertOk()
        ->assertSeeText(__('booking::booking.payment_status.refund_pending', [], $locale));
})->with(['en', 'ar'])->with(['pending' => 'pending', 'processing' => 'processing']);

it('shows captured, partially refunded, and refunded states on the customer invoice', function (string $locale, int $refundedMinor, string $status): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->confirmed()->create([
        'customer_id' => $customer->id,
        'total_minor' => 10_000,
    ]);
    $payment = Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'user_id' => $customer->id,
        'amount_minor' => 10_000,
        'amount_currency' => $booking->total_currency,
    ]);
    if ($refundedMinor > 0) {
        Refund::factory()->completed()->create([
            'payment_id' => $payment->id,
            'booking_id' => $booking->id,
            'amount_minor' => $refundedMinor,
            'amount_currency' => $booking->total_currency,
        ]);
    }

    $this->actingAs($customer, 'web')
        ->get(route('storefront.account.bookings.invoice', ['locale' => $locale, 'bookingPublicId' => $booking->public_id]))
        ->assertOk()
        ->assertSeeText(__('booking::booking.payment_status.'.$status, [], $locale));
})->with(['en', 'ar'])->with([
    'captured' => [0, 'paid'],
    'partially refunded' => [2_500, 'partially_refunded'],
    'fully refunded' => [10_000, 'refunded'],
]);

it('shows the published cancellation and refund policy summary before customer submission', function (string $locale): void {
    $this->seed(RefundPolicyCmsPageSeeder::class);
    $page = CmsPage::query()->where('slug', 'refund-policy')->sole();
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->draft()->create(['customer_id' => $customer->id]);
    $service = Service::factory()->digital()->published()->create();
    $vendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $service->vendor_profile_id,
    ]);
    BookingItem::factory()->digital()->create([
        'booking_vendor_id' => $vendor->id,
        'service_id' => $service->id,
        'line_total_minor' => 1000,
        'line_total_currency' => 'EGP',
    ]);

    $this->actingAs($customer)->get("/{$locale}/cart")
        ->assertOk()
        ->assertSee($page->getTranslation('meta_description', $locale), false)
        ->assertSee("/{$locale}/p/refund-policy", false);
})->with(['en', 'ar']);

it('renders active bilingual FAQ data and a working ticket fallback on the customer help page', function (string $locale): void {
    $category = FaqCategory::query()->create([
        'slug' => 'bookings',
        'name' => ['en' => 'Bookings', 'ar' => 'الحجوزات'],
        'is_active' => true,
    ]);
    $item = FaqItem::query()->create([
        'faq_category_id' => $category->id,
        'question' => ['en' => 'When is my booking received?', 'ar' => 'متى يتم استلام حجزي؟'],
        'answer' => ['en' => 'After submission.', 'ar' => 'بعد الإرسال.'],
        'is_active' => true,
    ]);
    FaqItem::query()->create([
        'faq_category_id' => $category->id,
        'question' => ['en' => 'Hidden draft question', 'ar' => 'سؤال غير منشور'],
        'answer' => ['en' => 'Hidden answer', 'ar' => 'إجابة مخفية'],
        'is_active' => false,
    ]);

    $this->get("/{$locale}/help")
        ->assertOk()
        ->assertSee($category->getTranslation('name', $locale))
        ->assertSee($item->getTranslation('question', $locale))
        ->assertDontSee('Hidden draft question')
        ->assertSee('data-support-ticket-form', false);
})->with(['en', 'ar']);

it('opens a real support ticket from the help page and shows its reference', function (string $locale): void {
    $response = $this->post("/{$locale}/help/tickets", [
        'subject' => 'Booking question',
        'body' => 'Please help with my booking.',
        'email' => 'customer@example.test',
    ]);

    $response->assertRedirect("/{$locale}/help");
    $ticket = SupportTicket::query()->sole();
    $response
        ->assertSessionHas('supportTicketReference', $ticket->public_id);

    $this->get("/{$locale}/help")->assertOk()
        ->assertSee('data-support-ticket-success', false)
        ->assertSee($ticket->public_id);
})->with(['en', 'ar']);

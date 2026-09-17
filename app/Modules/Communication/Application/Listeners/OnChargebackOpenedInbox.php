<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Payments\Domain\Events\ChargebackOpened;
use Illuminate\Support\Facades\DB;

class OnChargebackOpenedInbox
{
    public function __construct(
        private readonly RouteToAdminInboxAction $router,
    ) {}

    public function handle(ChargebackOpened $event): void
    {
        $row = DB::table('payments')
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('payments.id', $event->paymentId)
            ->select(['bookings.public_id as booking_number', 'payments.gateway_ref'])
            ->first();

        $bookingNumber = $row?->booking_number ?? "payment #{$event->paymentId}";
        $gatewayRef = $row?->gateway_ref ?? '';
        $amount = number_format($event->amountMinor / 100, 2);

        $this->router->execute(
            eventKey: 'chargeback.opened',
            severity: AdminInboxSeverity::Critical,
            sourceType: 'payment',
            sourceId: $event->paymentId,
            title: [
                'en' => "[CHARGEBACK] Booking #{$bookingNumber} — {$amount} {$event->amountCurrency}",
                'ar' => "[استرداد قسري] الحجز #{$bookingNumber} — {$amount} {$event->amountCurrency}",
            ],
            body: [
                'en' => "Chargeback opened on payment #{$event->paymentId} (ref: {$gatewayRef}) for booking #{$bookingNumber}. Amount: {$amount} {$event->amountCurrency}.",
                'ar' => "تم فتح استرداد قسري للدفع #{$event->paymentId} (مرجع: {$gatewayRef}) للحجز #{$bookingNumber}. المبلغ: {$amount} {$event->amountCurrency}.",
            ],
        );
    }
}

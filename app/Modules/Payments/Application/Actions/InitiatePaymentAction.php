<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Events\PaymentInitiated;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitiatePaymentAction
{
    public function __construct(
        private readonly EloquentPaymentRepository $payments,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @return array{payment_public_id: string, redirect_url: string, status: string}
     */
    public function execute(InitiatePaymentDto $dto): array
    {
        return DB::transaction(function () use ($dto): array {
            $booking = DB::table('bookings')
                ->where('id', $dto->bookingId)
                ->where('public_id', $dto->bookingPublicId)
                ->lockForUpdate()
                ->first();

            abort_if($booking === null, 404, 'Booking not found');
            abort_if((int) $booking->customer_id !== $dto->payerId, 404, 'Booking not found');
            abort_if($booking->lifecycle_status !== 'confirmed', 422, 'Booking is not ready for payment');
            abort_if($booking->payment_status !== 'unpaid', 422, 'Booking is already paid or refunded');

            abort_if(
                ! DB::table('booking_items')
                    ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
                    ->where('booking_vendors.booking_id', $dto->bookingId)
                    ->exists(),
                422,
                'Booking has no items',
            );

            $existingPayment = $this->payments->queryForBooking($dto->bookingId)
                ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Authorized->value, PaymentStatus::Captured->value])
                ->exists();
            abort_if($existingPayment, 409, 'Booking already has an active payment');

            abort_if(
                $dto->amount->getMinorAmount()->toInt() !== (int) $booking->total_minor
                || $dto->amount->getCurrency()->getCurrencyCode() !== (string) $booking->total_currency,
                422,
                'Payment amount does not match the booking total',
            );

            $intent = $this->gateway->initiate($dto);

            $payment = $this->payments->create([
                'public_id' => (string) Str::ulid(),
                'booking_id' => $dto->bookingId,
                'user_id' => $dto->payerId,
                'gateway' => 'paymob',
                'gateway_ref' => $intent->gatewayRef,
                'amount_minor' => $dto->amount->getMinorAmount()->toInt(),
                'amount_currency' => $dto->amount->getCurrency()->getCurrencyCode(),
                'method' => $dto->method,
                'status' => PendingState::class,
                'metadata' => $intent->rawResponse,
            ]);

            $payload = [
                'payment_public_id' => $payment->public_id,
                'redirect_url' => $intent->redirectUrl,
                'status' => $payment->status->getValue(),
            ];

            DB::afterCommit(fn () => event(new PaymentInitiated(
                $payment->id,
                $payment->booking_id,
                $payment->user_id,
                $payment->amount_minor,
                $payment->amount_currency,
            )));

            return $payload;
        });
    }
}

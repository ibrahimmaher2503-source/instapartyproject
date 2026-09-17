<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Customer;

use App\Modules\Payments\Application\Actions\InitiatePaymentAction;
use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Http\Requests\InitiatePaymentRequest;
use App\Modules\Shared\Http\ApiResponse;
use Brick\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * @group Customer - Payments
 */
class InitiatePaymentController
{
    public function __invoke(
        InitiatePaymentRequest $request,
        string $bookingPublicId,
        InitiatePaymentAction $action,
        PaymentsBookingReader $bookingReader,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $booking = $bookingReader->findByPublicId($bookingPublicId) ?? abort(404);
        abort_if($booking->customerId !== (int) $user->id, 404, 'Booking not found');
        $payload = $action->execute(new InitiatePaymentDto(
            bookingId: $booking->id,
            bookingPublicId: $booking->publicId,
            payerId: (int) $user->id,
            method: PaymentMethod::from((string) $request->string('method')),
            amount: Money::ofMinor((int) $booking->totalMinor, $booking->totalCurrency),
            idempotencyKey: (string) $request->header('Idempotency-Key'),
            route: 'payments.initiate',
            billingData: [
                'first_name' => Str::of((string) $user->name)->explode(' ')->first() ?: 'Customer',
                'last_name' => Str::of((string) $user->name)->explode(' ')->skip(1)->implode(' ') ?: 'Customer',
                'email' => (string) $user->email,
                'phone_number' => (string) $user->phone_e164,
                'apartment' => 'NA',
                'floor' => 'NA',
                'building' => 'NA',
                'street' => 'NA',
                'shipping_method' => 'PKG',
                'postal_code' => 'NA',
                'city' => 'Cairo',
                'state' => 'Cairo',
                'country' => 'EG',
            ],
        ));

        return ApiResponse::success(
            $payload,
            ['locale' => app()->getLocale(), 'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr'],
            201,
        );
    }
}

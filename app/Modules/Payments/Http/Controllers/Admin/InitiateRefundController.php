<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Admin;

use App\Modules\Payments\Application\Actions\InitiateRefundAction;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Http\Requests\InitiateRefundRequest;
use App\Modules\Payments\Http\Resources\RefundResource;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Payments\Infrastructure\Repositories\EloquentRefundRepository;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 */
class InitiateRefundController
{
    public function __invoke(
        InitiateRefundRequest $request,
        string $bookingPublicId,
        InitiateRefundAction $action,
        PaymentsBookingReader $bookingReader,
        EloquentPaymentRepository $payments,
        EloquentRefundRepository $refunds,
    ): JsonResponse {
        $booking = $bookingReader->findByPublicId($bookingPublicId) ?? abort(404, 'Booking not found');
        $requestKey = (string) $request->header('Idempotency-Key', '');
        $userId = (int) $request->user()->id;
        $idempotencyKeyPrefixes = $requestKey === ''
            ? []
            : $payments->queryForBooking($booking->id)
                ->pluck('id')
                ->map(fn (mixed $paymentId): string => $this->refundIdempotencyKeyPrefix(
                    $userId,
                    (int) $paymentId,
                    $requestKey,
                ))
                ->all();
        $existingRefund = $refunds->findByIdempotencyKeyPrefixesForBooking($booking->id, $idempotencyKeyPrefixes);
        $payment = $existingRefund !== null
            ? $payments->findById((int) $existingRefund->payment_id)
            : $payments->findActiveCaptureForBooking($booking->id);
        $payment ??= abort(409, 'No captured payment to refund');
        $refund = $action->execute(new InitiateRefundDto(
            paymentId: $payment->id,
            bookingId: $booking->id,
            initiatedBy: $userId,
            reasonCode: RefundReasonCode::from((string) $request->string('reason_code')),
            reasonNotes: (array) $request->input('reason_notes'),
            requestedAmountMinor: $request->has('amount_minor') ? (int) $request->integer('amount_minor') : null,
            idempotencyKey: $requestKey === ''
                ? null
                : $this->refundIdempotencyKey($userId, $payment->id, $requestKey, $request),
        ));

        return ApiResponse::success(
            (new RefundResource($refund))->toArray($request),
            ['locale' => app()->getLocale(), 'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr'],
            201,
        );
    }

    private function refundIdempotencyKeyPrefix(int $userId, int $paymentId, string $requestKey): string
    {
        // 63 digest chars + separator + 64 request-hash chars fit the 128-char column.
        return substr(hash('sha256', "refund|{$userId}|{$paymentId}|{$requestKey}"), 0, 63).':';
    }

    private function refundIdempotencyKey(
        int $userId,
        int $paymentId,
        string $requestKey,
        InitiateRefundRequest $request,
    ): string {
        $route = $request->route()?->getName() ?? $request->path();
        $requestHash = hash('sha256', implode('|', [
            $request->getMethod(),
            $request->getRequestUri(),
            $route,
            $request->getContent(),
        ]));

        // Keep the persisted value within refunds.idempotency_key's 128-char limit.
        return $this->refundIdempotencyKeyPrefix($userId, $paymentId, $requestKey).$requestHash;
    }
}

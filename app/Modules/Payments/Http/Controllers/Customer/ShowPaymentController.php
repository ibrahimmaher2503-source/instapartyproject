<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Customer;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Shared\Http\ApiResponse;

/**
 * @group Customer - Payments
 */
class ShowPaymentController
{
    public function __invoke(string $paymentPublicId)
    {
        $payment = Payment::query()
            ->where('public_id', $paymentPublicId)
            ->where('user_id', (int) auth()->id())
            ->firstOrFail();

        return ApiResponse::success(new PaymentResource($payment), ['locale' => app()->getLocale(), 'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr']);
    }
}

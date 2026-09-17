<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Resources;

use App\Modules\Payments\Domain\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 *
 * @response 200 {
 *   "data": [{
 *     "public_id": "01HW...",
 *     "booking_public_id": "01HX...",
 *     "amount_minor": 25000,
 *     "amount_currency": "EGP",
 *     "amount_formatted": "250.00 EGP",
 *     "status": "pending",
 *     "refund_method": "wallet",
 *     "expected_at": "2026-05-10",
 *     "completed_at": null,
 *     "created_at": "2026-05-01T12:00:00Z"
 *   }]
 * }
 */
class CustomerRefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $this->resolveLocale($request);
        $currency = $this->amount_currency ?? 'EGP';

        return [
            'public_id' => $this->public_id,
            'booking_public_id' => $this->booking?->public_id,
            'booking_reference' => $this->booking?->reference_number,
            'amount_minor' => $this->amount_minor,
            'amount_currency' => $currency,
            'amount_formatted' => $this->formatMoney($this->amount_minor, $currency, $locale),
            'status' => $this->status->value,
            'refund_method' => $this->resolveRefundMethod(),
            'expected_at' => $this->expectedAt(),
            'completed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function resolveRefundMethod(): string
    {
        return 'original_payment';
    }

    private function expectedAt(): ?string
    {
        if ($this->processed_at !== null) {
            return null;
        }

        return $this->created_at?->addDays(7)->toDateString();
    }

    private function formatMoney(int $minor, string $currency, string $locale): string
    {
        $amount = $minor / 100;

        if ($locale === 'ar') {
            return number_format($amount, 2).' ج.م';
        }

        return number_format($amount, 2).' '.$currency;
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $request->header('Accept-Language', 'en');

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }
}

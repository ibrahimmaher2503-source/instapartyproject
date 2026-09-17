<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Resources;

use App\Modules\Settlement\Domain\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallet
 *
 * @response 200 {
 *   "data": {
 *     "public_id": "01HW...",
 *     "currency": "EGP",
 *     "balance_minor": 50000,
 *     "balance_formatted": "500.00 EGP",
 *     "pending_refunds_minor": 10000,
 *     "pending_refunds_formatted": "100.00 EGP"
 *   }
 * }
 * @response 200 scenario="ar" {
 *   "data": {
 *     "public_id": "01HW...",
 *     "currency": "EGP",
 *     "balance_minor": 50000,
 *     "balance_formatted": "٥٠٠٫٠٠ ج.م",
 *     "pending_refunds_minor": 10000,
 *     "pending_refunds_formatted": "١٠٠٫٠٠ ج.م"
 *   }
 * }
 */
class CustomerWalletResource extends JsonResource
{
    private int $pendingRefundsMinor;

    public function withPendingRefunds(int $minor): static
    {
        $this->pendingRefundsMinor = $minor;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $this->resolveLocale($request);
        $currency = $this->currency;
        $pending = $this->pendingRefundsMinor ?? 0;

        return [
            'public_id' => $this->public_id,
            'currency' => $currency,
            'balance_minor' => $this->balance_minor,
            'balance_formatted' => $this->formatMoney($this->balance_minor, $currency, $locale),
            'pending_refunds_minor' => $pending,
            'pending_refunds_formatted' => $this->formatMoney($pending, $currency, $locale),
        ];
    }

    private function formatMoney(int $minor, string $currency, string $locale): string
    {
        $amount = $minor / 100;

        if ($locale === 'ar') {
            $formatted = number_format($amount, 2, '.', ',');

            return $formatted.' ج.م';
        }

        return number_format($amount, 2).' '.$currency;
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $request->header('Accept-Language', 'en');

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }
}

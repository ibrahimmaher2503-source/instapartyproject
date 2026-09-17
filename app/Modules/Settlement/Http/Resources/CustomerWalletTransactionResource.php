<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Resources;

use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WalletLedgerEntry
 *
 * @response 200 {
 *   "data": [{
 *     "direction": "credit",
 *     "amount_minor": 50000,
 *     "amount_formatted": "500.00 EGP",
 *     "currency": "EGP",
 *     "description": "Refund for booking #INS-001",
 *     "running_balance_minor": 150000,
 *     "running_balance_formatted": "1,500.00 EGP",
 *     "posted_at": "2026-05-01T12:00:00Z",
 *     "expected_at": "2026-05-06T12:00:00Z"
 *   }]
 * }
 */
class CustomerWalletTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $this->resolveLocale($request);
        $currency = $this->currency;

        return [
            'direction' => $this->direction?->value ?? 'credit',
            'amount_minor' => $this->amount_minor,
            'amount_formatted' => $this->formatMoney($this->amount_minor, $currency, $locale),
            'currency' => $currency,
            'description' => $this->resolveDescription($locale),
            'running_balance_minor' => $this->running_balance_minor,
            'running_balance_formatted' => $this->running_balance_minor !== null
                ? $this->formatMoney($this->running_balance_minor, $currency, $locale)
                : null,
            'posted_at' => $this->posted_at?->toISOString() ?? $this->created_at?->toISOString(),
            'expected_at' => $this->resolveExpectedAt(),
        ];
    }

    private function resolveDescription(string $locale): string
    {
        if ($this->description_key !== null) {
            $translated = __($this->description_key, $this->description_params ?? [], $locale);
            if ($translated !== $this->description_key) {
                return (string) $translated;
            }
        }

        return $this->entry_type->value;
    }

    /**
     * Returns an ISO-8601 ETA string for pending refund credit entries, null otherwise.
     *
     * A refund credit is considered "pending" when `posted_at` is null — the entry
     * has been recorded but funds have not yet been posted to the wallet. Once posted,
     * the refund is settled and no ETA is needed.
     */
    private function resolveExpectedAt(): ?string
    {
        if ($this->entry_type !== LedgerEntryType::RefundCreditCustomer) {
            return null;
        }

        // If the entry is already posted, the refund has settled — no ETA needed.
        if ($this->posted_at !== null) {
            return null;
        }

        $slaDays = (int) config('settlement.refund_sla_days', 5);
        $base = $this->created_at ?? now();

        return $base->copy()->addDays($slaDays)->toISOString();
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

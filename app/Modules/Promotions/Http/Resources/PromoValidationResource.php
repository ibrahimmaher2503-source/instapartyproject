<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Resources;

use App\Modules\Promotions\Application\DTOs\PromoValidationResultDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromoValidationResultDTO
 *
 * @response 200 scenario="valid" {
 *   "data": {
 *     "valid": true,
 *     "code": "SUMMER25",
 *     "discount_type": "percentage",
 *     "discount_percent": 25,
 *     "discount_minor": 25000,
 *     "discount_currency": "EGP",
 *     "discount_formatted": "250.00 EGP",
 *     "new_total_minor": 75000,
 *     "new_total_formatted": "750.00 EGP",
 *     "message": null
 *   }
 * }
 * @response 200 scenario="invalid" {
 *   "data": {
 *     "valid": false,
 *     "code": "OLD2024",
 *     "discount_type": null,
 *     "discount_percent": null,
 *     "discount_minor": null,
 *     "discount_currency": "EGP",
 *     "discount_formatted": null,
 *     "new_total_minor": null,
 *     "new_total_formatted": null,
 *     "message": "This promo code has expired.",
 *     "rejection_reason": "PROMO_EXPIRED",
 *     "rejection_message": {
 *       "en": "This promo code has expired.",
 *       "ar": "انتهت صلاحية رمز الخصم."
 *     }
 *   }
 * }
 */
class PromoValidationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PromoValidationResultDTO $dto */
        $dto = $this->resource;

        $locale = $this->resolveLocale($request);
        $currency = $dto->promoCode?->discount_currency ?? 'EGP';

        return [
            'valid' => $dto->valid,
            'code' => $dto->promoCode?->code,
            'discount_type' => $dto->promoCode?->type->value,
            'discount_percent' => $dto->promoCode?->discount_percent,
            'discount_minor' => $dto->discount?->getMinorAmount()->toInt(),
            'discount_currency' => $currency,
            'discount_formatted' => $dto->discount !== null
                ? $this->formatMoney($dto->discount->getMinorAmount()->toInt(), $currency, $locale)
                : null,
            'new_total_minor' => $dto->newTotal?->getMinorAmount()->toInt(),
            'new_total_formatted' => $dto->newTotal !== null
                ? $this->formatMoney($dto->newTotal->getMinorAmount()->toInt(), $currency, $locale)
                : null,
            'message' => $dto->errorMessage($locale),
            'rejection_reason' => $dto->rejectionReason?->value,
            'rejection_message' => $dto->rejectionReason !== null ? [
                'en' => $dto->rejectionReason->labelEn(),
                'ar' => $dto->rejectionReason->labelAr(),
            ] : null,
        ];
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

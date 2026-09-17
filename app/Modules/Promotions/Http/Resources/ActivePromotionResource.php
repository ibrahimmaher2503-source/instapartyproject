<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Resources;

use App\Modules\Promotions\Domain\Enums\PromoCodeScope;
use App\Modules\Promotions\Domain\Enums\PromoCodeType;
use App\Modules\Promotions\Domain\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromoCode
 *
 * @response 200 scenario="active percentage promo" {
 *   "public_id": "01JC7G3F0AY3T9V5K0X9N1QY3S",
 *   "code": "PARTY10",
 *   "title": "10% off your party",
 *   "description": "Use code PARTY10 at checkout.",
 *   "scope": "global",
 *   "category_public_id": null,
 *   "vendor_public_id": null,
 *   "service_public_id": null,
 *   "discount": { "kind": "percentage", "value_minor": 1000, "currency": "EGP" },
 *   "starts_at": "2026-05-29T00:00:00+00:00",
 *   "ends_at": "2026-06-15T23:59:59+00:00",
 *   "min_order_minor": 0,
 *   "currency": "EGP"
 * }
 *
 * Note (Feature 054 deviation): the locked `promo_codes` schema has no title/description
 * columns, so both are synthesized from the discount terms and localized here, at the
 * Resource layer (Constitution IV). `scope: 'platform'` is exposed as `'global'`.
 */
class ActivePromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PromoCode $promo */
        $promo = $this->resource;

        $locale = $this->resolveLocale($request);
        $currency = $promo->type === PromoCodeType::Percentage
            ? $promo->discount_currency
            : ($promo->discount_currency ?: 'EGP');

        $scopePublicId = $promo->getAttribute('scope_public_id');

        return [
            'public_id' => $promo->public_id,
            'code' => $promo->code,
            'title' => $this->title($promo, $locale, $currency),
            'description' => $this->description($promo, $locale),
            'scope' => $this->publicScope($promo->scope),
            'category_public_id' => $promo->scope === PromoCodeScope::Category ? $scopePublicId : null,
            'vendor_public_id' => $promo->scope === PromoCodeScope::Vendor ? $scopePublicId : null,
            'service_public_id' => $promo->scope === PromoCodeScope::Service ? $scopePublicId : null,
            'discount' => [
                'kind' => $promo->type->value,
                'value_minor' => $this->discountValueMinor($promo),
                'currency' => $currency,
            ],
            'starts_at' => $promo->starts_at?->toIso8601String(),
            'ends_at' => $promo->expires_at?->toIso8601String(),
            'min_order_minor' => $promo->min_order_minor ?? 0,
            'currency' => $currency,
        ];
    }

    /**
     * For 'percentage' the value is basis points (10% => 1000); for 'fixed' it is piastres.
     */
    private function discountValueMinor(PromoCode $promo): int
    {
        return match ($promo->type) {
            PromoCodeType::Percentage => (int) ($promo->discount_percent ?? 0) * 100,
            PromoCodeType::Fixed => (int) ($promo->discount_minor ?? 0),
        };
    }

    private function title(PromoCode $promo, string $locale, string $currency): string
    {
        if ($promo->type === PromoCodeType::Percentage) {
            $percent = (int) ($promo->discount_percent ?? 0);

            return $locale === 'ar'
                ? "خصم {$percent}٪ على حجزك"
                : "{$percent}% off your party";
        }

        $amount = number_format(((int) ($promo->discount_minor ?? 0)) / 100, 2);

        return $locale === 'ar'
            ? "خصم {$amount} ج.م"
            : "{$amount} {$currency} off";
    }

    private function description(PromoCode $promo, string $locale): string
    {
        return $locale === 'ar'
            ? "استخدم الرمز {$promo->code} عند الدفع."
            : "Use code {$promo->code} at checkout.";
    }

    private function publicScope(PromoCodeScope $scope): string
    {
        return match ($scope) {
            PromoCodeScope::Platform => 'global',
            PromoCodeScope::Category => 'category',
            PromoCodeScope::Vendor => 'vendor',
            PromoCodeScope::Service => 'service',
        };
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }
}

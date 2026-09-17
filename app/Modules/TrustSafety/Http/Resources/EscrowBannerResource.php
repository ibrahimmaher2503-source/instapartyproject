<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @response 200 {
 *   "data": {
 *     "enabled": true,
 *     "text": "Your payment is held securely until your event is complete."
 *   }
 * }
 * @response 200 scenario="disabled" {
 *   "data": {
 *     "enabled": false,
 *     "text": null
 *   }
 * }
 */
class EscrowBannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $this->resolveLocale($request);
        $enabled = (bool) ($this->resource['enabled'] ?? false);
        $textJson = $this->resource['text'] ?? null;

        $text = null;
        if ($enabled && $textJson !== null) {
            $decoded = is_array($textJson) ? $textJson : json_decode($textJson, true);
            $text = $decoded[$locale] ?? $decoded['en'] ?? null;
        }

        return [
            'enabled' => $enabled,
            'text' => $text,
        ];
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $request->header('Accept-Language', 'en');

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }
}

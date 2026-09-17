<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Resources;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyBalancesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = str_starts_with((string) $request->header('Accept-Language', app()->getLocale()), 'ar') ? 'ar' : 'en';

        $name = $this->resource['vendor_name'] ?? null;
        if (is_array($name)) {
            $name = $name[$locale] ?? ($name['en'] ?? null);
        }

        $expiresAt = $this->resource['expires_at'];
        if ($expiresAt !== null && ! $expiresAt instanceof DateTimeInterface) {
            $expiresAt = Carbon::parse($expiresAt);
        }

        $isExpiringSoon = $expiresAt !== null
            && Carbon::instance($expiresAt)->diffInDays(now(), false) >= -30;

        return [
            'vendor_public_id' => $this->resource['vendor_public_id'],
            'vendor_name' => $name,
            'balance_points' => (int) ($this->resource['balance_points'] ?? 0),
            'balance_minor_equivalent' => (int) ($this->resource['balance_minor_equivalent'] ?? 0),
            'currency' => (string) ($this->resource['currency'] ?? 'EGP'),
            'expires_at' => $expiresAt !== null ? Carbon::instance($expiresAt)->toISOString() : null,
            'is_expiring_soon' => $isExpiringSoon,
        ];
    }
}

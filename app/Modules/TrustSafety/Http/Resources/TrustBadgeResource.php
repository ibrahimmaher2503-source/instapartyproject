<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Http\Resources;

use App\Modules\TrustSafety\Domain\Models\TrustBadge;
use App\Modules\TrustSafety\Domain\Models\VendorBadgeAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrustBadge
 *
 * @response 200 {
 *   "data": [{
 *     "public_id": "01HW...",
 *     "name": "ID Verified",
 *     "description": "This vendor has verified their identity.",
 *     "level": 1,
 *     "icon_url": "https://cdn.../thumb/badge.webp",
 *     "assigned_at": "2026-05-01T12:00:00Z"
 *   }]
 * }
 */
class TrustBadgeResource extends JsonResource
{
    public function __construct(TrustBadge $resource, private ?VendorBadgeAssignment $assignment = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $this->resolveLocale($request);

        return [
            'public_id' => $this->public_id,
            'name' => $this->getTranslation('name', $locale),
            'description' => $this->getTranslation('description', $locale),
            'level' => $this->level,
            'icon_url' => $this->getFirstMediaUrl('trust-badge-icon', 'thumb') ?: null,
            'assigned_at' => $this->assignment?->assigned_at?->toISOString(),
        ];
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $request->header('Accept-Language', 'en');

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Resources;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin VendorProfile */
class VendorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', 'en') === 'ar' ? 'ar' : 'en';

        return [
            'public_id' => $this->public_id,
            'business_name' => $this->getTranslation('business_name', $locale, false)
                ?? $this->getTranslation('business_name', 'en', false)
                ?? '',
            'bio' => $this->bio
                ? ($this->getTranslation('bio', $locale, false) ?? $this->getTranslation('bio', 'en', false))
                : null,
            'is_verified' => $this->approval_status instanceof ApprovedState,
            'city' => $this->primaryCity
                ? ['name' => $this->primaryCity->getTranslation('name', $locale, false)
                    ?? $this->primaryCity->getTranslation('name', 'en', false)
                    ?? $this->primaryCity->name ?? '']
                : null,
            'rating_avg' => (float) $this->rating_avg > 0 ? (float) $this->rating_avg : null,
            'rating_count' => (int) $this->rating_count,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'cover_image_url' => $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null,
            'avg_response_hours' => $this->response_time_avg_minutes !== null
                ? (int) round((int) $this->response_time_avg_minutes / 60)
                : null,
            'product_types' => $this->approvedTypes
                ->map(fn ($t) => $t->product_type->value)
                ->values()
                ->all(),
            'services_count' => (int) ($this->services_count ?? 0),
            'member_since' => $this->created_at?->toIso8601String(),
        ];
    }
}

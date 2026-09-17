<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Application\Services\StorefrontText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @response 200 {
 *   "data": {
 *     "id": "01J9X7K3M2WZE0X8H4Q9N5T7V2",
 *     "business_name": "InstaParty QA Events",
 *     "business_name_translations": {"en": "InstaParty QA Events", "ar": "InstaParty QA Events Arabic"},
 *     "slug": "instaparty-qa-events",
 *     "bio": null,
 *     "business_type": "individual",
 *     "approval_status": "approved",
 *     "primary_governorate_id": "01J9X7M88HTR9V76J4MVY8FDVT",
 *     "primary_city_id": "01J9X7N4MMQS65F5TQJY6HPPVK",
 *     "approved_product_types": ["rental"],
 *     "logo_url": null,
 *     "cover_image_url": null,
 *     "created_at": "2026-09-06T12:00:00+00:00"
 *   },
 *   "meta": {"locale": "en", "direction": "ltr"},
 *   "errors": []
 * }
 */
class VendorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var VendorProfile $profile */
        $profile = $this->resource;
        $locale = app()->getLocale();

        return [
            'id' => $profile->public_id,
            'business_name' => app(StorefrontText::class)->translation($profile, 'business_name', $locale),
            'business_name_translations' => $profile->getTranslations('business_name'),
            'slug' => $profile->slug,
            'bio' => $profile->bio ? $profile->getTranslation('bio', $locale) : null,
            'business_type' => $profile->business_type?->value,
            'approval_status' => $profile->approval_status->getMorphClass(),
            'primary_governorate_id' => $profile->primaryGovernorate?->public_id,
            'primary_city_id' => $profile->primaryCity?->public_id,
            'approved_product_types' => $this->whenLoaded(
                'approvedTypes',
                fn () => $profile->approvedTypes
                    ->map(fn (VendorApprovedProductType $row): string => $row->product_type->value)
                    ->values()
            ),
            'logo_url' => $profile->logo_path
                ? Storage::disk('public')->url($profile->logo_path)
                : ($profile->getFirstMediaUrl('portfolio', 'thumb') ?: null),
            'cover_image_url' => $profile->cover_path
                ? Storage::disk('public')->url($profile->cover_path)
                : null,
            'created_at' => $profile->created_at->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerVendorReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', 'en');
        $locale = str_starts_with($locale, 'ar') ? 'ar' : 'en';

        $fallbackInitial = $locale === 'ar' ? 'م' : 'C';
        $reviewer = $this->reviewer;
        $authorInitial = $fallbackInitial;
        if ($reviewer && ! empty($reviewer->name)) {
            $firstChar = mb_substr(trim($reviewer->name), 0, 1);
            if ($firstChar !== '') {
                $authorInitial = $firstChar;
            }
        }

        return [
            'public_id' => $this->public_id,
            'rating' => $this->rating,
            'body' => $this->body,
            'author_initial' => $authorInitial,
            'created_at' => $this->created_at?->toISOString(),
            'vendor_response' => null, // ReviewResponse relation not yet implemented
        ];
    }
}

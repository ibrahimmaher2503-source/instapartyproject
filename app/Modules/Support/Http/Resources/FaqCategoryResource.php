<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', 'en');

        return [
            'public_id' => $this->public_id,
            'name' => $this->getTranslation('name', $locale, false) ?: $this->getTranslation('name', 'en'),
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'items' => FaqItemResource::collection($this->whenLoaded('items')),
        ];
    }
}

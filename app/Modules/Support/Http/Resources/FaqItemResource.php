<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', 'en');

        return [
            'public_id' => $this->public_id,
            'question' => $this->getTranslation('question', $locale, false) ?: $this->getTranslation('question', 'en'),
            'answer' => $this->getTranslation('answer', $locale, false) ?: $this->getTranslation('answer', 'en'),
            'sort_order' => $this->sort_order,
            'category' => new FaqCategoryResource($this->whenLoaded('category')),
        ];
    }
}

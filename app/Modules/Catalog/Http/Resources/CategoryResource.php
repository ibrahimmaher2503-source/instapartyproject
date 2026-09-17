<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Domain\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Customer-facing category. Frontend type: `Category`.
 *
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->getTranslation('name', app()->getLocale(), useFallbackLocale: true),
            'slug' => $this->code,
            'icon_url' => $this->icon_path ? Storage::disk('public')->url($this->icon_path) : null,
        ];
    }
}

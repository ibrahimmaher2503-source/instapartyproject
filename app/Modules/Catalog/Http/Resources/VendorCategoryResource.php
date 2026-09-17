<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Domain\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vendor-facing category reference. Extends the customer shape with the fields
 * a vendor's service-create form needs: which product types the category
 * allows, and its parent (for hierarchy display).
 *
 * @mixin Category
 */
class VendorCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Integer id is the write contract for services.category_id —
            // clients were inferring it from listing order, which corrupts on
            // any category insert/reorder (live audit 2026-06-06 §4.1).
            'id' => $this->id,
            'public_id' => $this->public_id,
            'name' => $this->getTranslation('name', app()->getLocale(), useFallbackLocale: true),
            'slug' => $this->code,
            'parent_id' => $this->parent?->public_id,
            'allowed_product_types' => $this->allowed_product_types ?? [],
        ];
    }
}

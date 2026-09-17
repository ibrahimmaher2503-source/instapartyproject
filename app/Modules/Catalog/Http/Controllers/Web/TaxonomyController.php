<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use Illuminate\Http\RedirectResponse;

class TaxonomyController
{
    public function category(string $identifier): RedirectResponse
    {
        $category = Category::query()
            ->active()
            ->where(fn ($query) => $query->where('code', $identifier)->orWhere('public_id', $identifier))
            ->firstOrFail();

        return redirect()->route('storefront.search', [
            'locale' => app()->getLocale(),
            'category_slug' => $category->code,
        ]);
    }

    public function occasion(string $identifier): RedirectResponse
    {
        $occasion = Occasion::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('code', $identifier)->orWhere('public_id', $identifier))
            ->firstOrFail();

        return redirect()->route('storefront.search', [
            'locale' => app()->getLocale(),
            'occasion' => $occasion->code,
        ]);
    }
}

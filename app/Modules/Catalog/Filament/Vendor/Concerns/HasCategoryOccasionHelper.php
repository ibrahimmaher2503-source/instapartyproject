<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Concerns;

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use Filament\Forms\Get;

/**
 * Shared helper text for the Category select on the three vendor service forms.
 *
 * Services have no occasion column; they surface under occasions through their
 * category (occasion_category pivot). This shows the resolved occasions as
 * read-only guidance for vendors who think in occasion terms.
 */
trait HasCategoryOccasionHelper
{
    protected static function categoryOccasionHelper(Get $get): ?string
    {
        $categoryId = $get('category_id');

        if (! $categoryId) {
            return __('vendor-portal.services.occasion_helper_empty');
        }

        $occasions = Category::query()
            ->with('occasions')
            ->find($categoryId)?->occasions
            ->map(fn (Occasion $o): string => $o->getTranslation('name', app()->getLocale()))
            ->implode(__('vendor-portal.services.occasion_separator'));

        return $occasions
            ? __('vendor-portal.services.occasion_helper', ['occasions' => $occasions])
            : __('vendor-portal.services.occasion_helper_none');
    }
}

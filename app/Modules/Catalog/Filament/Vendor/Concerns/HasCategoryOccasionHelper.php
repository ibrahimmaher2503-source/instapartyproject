<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Concerns;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Validation\Rule;

/**
 * Shared helper text for the Category select on the three vendor service forms.
 *
 * Services have no occasion column; they surface under occasions through their
 * category (occasion_category pivot). This shows the resolved occasions as
 * read-only guidance for vendors who think in occasion terms.
 */
trait HasCategoryOccasionHelper
{
    /** @return array<Select> */
    protected static function serviceCategoryFields(ProductType $type): array
    {
        return [
            Select::make('main_category_id')
                ->label(__('catalog.main_category'))
                ->options(fn () => Category::query()->active()->forProductType($type)->whereNull('parent_id')
                    ->whereHas('children', fn ($query) => $query->active()->forProductType($type))
                    ->orderBy('sort_order')->get()->mapWithKeys(fn (Category $category): array => [
                        $category->id => $category->getTranslation('name', app()->getLocale()),
                    ]))
                ->searchable()->preload()->required()->live()->dehydrated(false)
                ->rules(['integer', Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->whereNull('parent_id')->whereNull('deleted_at')->where('is_active', true)
                    ->whereJsonContains('allowed_product_types', $type->value)
                    ->whereExists(fn ($children) => $children->selectRaw('1')->from('categories as eligible_subcategories')
                        ->whereColumn('eligible_subcategories.parent_id', 'categories.id')
                        ->whereNull('eligible_subcategories.deleted_at')
                        ->where('eligible_subcategories.is_active', true)
                        ->whereJsonContains('eligible_subcategories.allowed_product_types', $type->value)))])
                ->validationMessages(['exists' => __('catalog.category_pair_invalid')])
                ->afterStateUpdated(function (Set $set, Select $component): void {
                    $set('category_id', null);
                    static::resetCategorySchemaFields($set, $component);
                })
                ->helperText(fn (?Service $record): string => $record !== null && $record->category?->parent_id === null
                    ? __('catalog.legacy_category_help')
                    : __('catalog.vendor_category_requires_subcategory_help')),
            Select::make('category_id')
                ->label(__('catalog.subcategory'))
                ->key(fn (Get $get): string => 'category-'.($get('main_category_id') ?? 'none'))
                ->placeholder(__('catalog.choose_main_category_first'))
                ->disabled(fn (Get $get): bool => ! filled($get('main_category_id')))
                ->options(fn (Get $get) => Category::query()->active()->forProductType($type)
                    ->where('parent_id', is_numeric($get('main_category_id')) ? (int) $get('main_category_id') : 0)
                    ->orderBy('sort_order')->get()->mapWithKeys(fn (Category $category): array => [
                        $category->id => $category->getTranslation('name', app()->getLocale()),
                    ]))
                ->searchable()->preload()->required()->live()
                ->afterStateUpdated(fn (Set $set, Select $component) => static::resetCategorySchemaFields($set, $component))
                ->rules(fn (Get $get): array => ['integer', Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('parent_id', is_numeric($get('main_category_id')) ? (int) $get('main_category_id') : 0)
                    ->whereNull('deleted_at')->where('is_active', true)
                    ->whereJsonContains('allowed_product_types', $type->value))])
                ->validationMessages(['exists' => __('catalog.category_pair_invalid')])
                ->helperText(fn (Get $get): ?string => static::categoryOccasionHelper($get)),
        ];
    }

    protected static function categoryOccasionHelper(Get $get): ?string
    {
        $categoryId = $get('category_id');

        if (! $categoryId) {
            return __('vendor-portal.services.occasion_helper_empty');
        }

        $occasions = Category::query()
            ->with('occasions')
            ->find($categoryId)?->occasions
            ->filter(fn (Occasion $occasion): bool => $occasion->is_active)
            ->map(fn (Occasion $o): string => $o->getTranslation('name', app()->getLocale()))
            ->implode(__('vendor-portal.services.occasion_separator'));

        return $occasions
            ? __('vendor-portal.services.occasion_helper', ['occasions' => $occasions])
            : __('vendor-portal.services.occasion_helper_none');
    }
}

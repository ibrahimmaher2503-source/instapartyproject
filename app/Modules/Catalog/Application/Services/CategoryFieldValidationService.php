<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Services;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;

class CategoryFieldValidationService
{
    /** @var array<string, array<string, list<string>>> */
    private array $cache = [];

    /**
     * Return Laravel validation rules for all category-specific fields
     * that apply to the given category and product type.
     *
     * Only fields that are required OR have explicit validation_rules are included.
     * Results are memoized per (category_id × product_type) for the request lifecycle.
     *
     * @return array<string, list<string>>
     */
    public function rulesFor(int $categoryId, ProductType $productType): array
    {
        $cacheKey = "{$categoryId}:{$productType->value}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $schemas = CategoryFieldSchema::where('category_id', $categoryId)
            ->where('product_type', $productType)
            ->get();

        $rules = [];

        foreach ($schemas as $schema) {
            $hasExplicitRules = ! empty($schema->validation_rules);

            if (! $schema->is_required && ! $hasExplicitRules) {
                continue;
            }

            $fieldRules = $hasExplicitRules ? $schema->validation_rules : [];

            if ($schema->is_required && ! in_array('required', $fieldRules, true)) {
                array_unshift($fieldRules, 'required');
            }

            $rules[$schema->field_key] = $fieldRules;
        }

        $this->cache[$cacheKey] = $rules;

        return $rules;
    }

    /**
     * Return a bilingual error message for a required category field.
     *
     * @return array{en: string, ar: string}
     */
    public function bilingualErrorFor(CategoryFieldSchema $schema): array
    {
        $labelEn = $schema->getTranslation('field_label', 'en');
        $labelAr = $schema->getTranslation('field_label', 'ar');

        return [
            'en' => "The {$labelEn} field is required.",
            'ar' => "حقل {$labelAr} مطلوب.",
        ];
    }
}

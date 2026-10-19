<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Services;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryFieldValidationService
{
    /** @var array<string, array<string, list<mixed>>> */
    private array $cache = [];

    /**
     * Shared parent fields are inherited; the closest category owns an override.
     * Resolve the current definition. Rule caching below is limited to this instance.
     *
     * @return Collection<int, CategoryFieldSchema>
     */
    public function schemasFor(int $categoryId, ProductType $productType): Collection
    {
        $path = [];
        $category = Category::query()->find($categoryId);
        while ($category !== null && ! in_array($category->id, $path, true)) {
            $path[] = $category->id;
            $category = $category->parent_id !== null ? Category::query()->find($category->parent_id) : null;
        }

        $schemas = CategoryFieldSchema::query()->whereIn('category_id', $path)
            ->where('product_type', $productType)->get();
        $resolved = new Collection;
        foreach (array_reverse($path) as $id) {
            foreach ($schemas->where('category_id', $id) as $schema) {
                $resolved->put($schema->field_key, $schema);
            }
        }

        return $resolved->sortBy('sort_order')->values();
    }

    /** @return array<string, string> */
    public function optionsFor(CategoryFieldSchema $schema, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $options = [];
        foreach ($schema->options ?? [] as $key => $option) {
            if (is_array($option) && array_key_exists('value', $option)) {
                $value = (string) $option['value'];
                $label = $option['label'] ?? $value;
            } else {
                if (is_array($option) && array_is_list($schema->options ?? [])) {
                    continue;
                }
                $value = array_is_list($schema->options ?? []) ? (string) $option : (string) $key;
                $label = $option;
            }
            $options[$value] = is_array($label)
                ? (string) ($label[$locale] ?? $label['en'] ?? $value)
                : (string) $label;
        }

        return $options;
    }

    /** @return array<string, list<mixed>> */
    public function rulesFor(int $categoryId, ProductType $productType): array
    {
        $cacheKey = "{$categoryId}:{$productType->value}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        $rules = [];
        foreach ($this->schemasFor($categoryId, $productType) as $schema) {
            // Admin's KeyValue widget stores rule => argument; existing seeds use lists.
            $explicit = [];
            foreach ($schema->validation_rules ?? [] as $key => $value) {
                if (is_int($key)) {
                    $explicit[] = $value;
                } elseif ($value !== false && $value !== null && $value !== '') {
                    $explicit[] = $value === true ? $key : $key.':'.$value;
                }
            }
            $fieldRules = match ($schema->field_type) {
                'text' => ['string'],
                'number' => ['numeric'],
                'boolean' => ['boolean'],
                'select' => ['string', Rule::in(array_keys($this->optionsFor($schema)))],
                'multiselect' => ['array'],
                'date' => ['date_format:Y-m-d'],
                default => ['prohibited'],
            };
            if ($schema->is_required) {
                $explicit = array_values(array_filter($explicit, fn (string $rule): bool => ! in_array($rule, ['sometimes', 'nullable'], true)
                    && ! str_starts_with($rule, 'exclude')));
            }
            array_unshift($fieldRules, $schema->is_required || in_array('required', $explicit, true) ? 'required' : 'nullable');
            $rules[$schema->field_key] = array_merge($fieldRules, $explicit);
            if ($schema->field_type === 'multiselect') {
                $rules[$schema->field_key.'.*'] = ['string', Rule::in(array_keys($this->optionsFor($schema)))];
            }
        }

        return $this->cache[$cacheKey] = $rules;
    }

    /**
     * Validate only schema-owned values, never arbitrary base service attributes.
     * Callers keep this payload separate from price, ownership and lifecycle fields.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function validateValues(int $categoryId, ProductType $productType, array $values, string $errorPrefix = '', ?Service $existingService = null): array
    {
        $schemas = $this->schemasFor($categoryId, $productType);
        $validator = Validator::make($values, $this->rulesForEdit($categoryId, $productType, $existingService), [],
            $schemas->mapWithKeys(fn (CategoryFieldSchema $schema): array => [
                $schema->field_key => $schema->getTranslation('field_label', app()->getLocale()),
            ])->all());
        $validator->after(function ($validator) use ($values, $schemas): void {
            foreach (array_diff(array_keys($values), $schemas->pluck('field_key')->all()) as $key) {
                $validator->errors()->add($key, __('catalog.category_field_unknown'));
            }
        });

        try {
            return $validator->validate();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => [$errorPrefix.$key => $messages])->all());
        }
    }

    /** @return array<string, list<mixed>> */
    public function rulesForEdit(int $categoryId, ProductType $productType, ?Service $service = null): array
    {
        $rules = $this->rulesFor($categoryId, $productType);

        if ($service === null || $service->category_id !== $categoryId || $service->product_type !== $productType) {
            return $rules;
        }

        foreach ($rules as $key => $fieldRules) {
            // Missing historical values may stay empty; existing values cannot be cleared.
            if (! str_contains($key, '.') && blank(($service->category_field_values ?? [])[$key] ?? null)) {
                $rules[$key] = array_values(array_filter($fieldRules, fn ($rule): bool => $rule !== 'required'));
                array_unshift($rules[$key], 'nullable');
            }
        }

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

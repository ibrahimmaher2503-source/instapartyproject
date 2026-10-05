<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Services;

use App\Modules\Catalog\Domain\Models\Service;

class CategoryFieldPresenter
{
    public function __construct(private readonly CategoryFieldValidationService $validator) {}

    /** @return list<array{key: string, label: string, value: string}> */
    public function forService(Service $service): array
    {
        $items = [];
        $values = $service->category_field_values ?? [];
        foreach ($this->validator->schemasFor($service->category_id, $service->product_type) as $schema) {
            $value = $values[$schema->field_key] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $options = $this->validator->optionsFor($schema);
            $formatted = match ($schema->field_type) {
                'select' => is_scalar($value) ? ($options[(string) $value] ?? null) : null,
                'multiselect' => is_array($value)
                    ? implode(', ', array_values(array_intersect_key($options, array_flip(array_filter($value, 'is_string'))))) : null,
                'boolean' => in_array($value, [true, 1, '1', false, 0, '0'], true)
                    ? __($value ? 'storefront.common.yes' : 'storefront.common.no') : null,
                default => is_scalar($value) ? (string) $value : null,
            };
            if ($formatted !== null && $formatted !== '') {
                $items[] = ['key' => $schema->field_key,
                    'label' => $schema->getTranslation('field_label', app()->getLocale()), 'value' => $formatted];
            }
        }

        return $items;
    }
}

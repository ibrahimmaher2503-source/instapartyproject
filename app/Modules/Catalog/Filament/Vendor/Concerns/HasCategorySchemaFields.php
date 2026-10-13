<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Concerns;

use App\Modules\Catalog\Application\Services\CategoryFieldValidationService;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;

trait HasCategorySchemaFields
{
    protected static function categorySchemaSection(ProductType $type): Section
    {
        return Section::make(__('catalog.category_specific_fields'))
            ->key('category-schema-fields')
            ->visible(fn (Get $get): bool => is_numeric($get('category_id'))
                && app(CategoryFieldValidationService::class)->schemasFor((int) $get('category_id'), $type)->isNotEmpty())
            ->schema(function (Get $get, ?Service $record) use ($type): array {
                $validator = app(CategoryFieldValidationService::class);
                $categoryId = is_numeric($get('category_id')) ? (int) $get('category_id') : 0;
                $rules = $validator->rulesForEdit($categoryId, $type, $record);

                return $validator->schemasFor($categoryId, $type)->map(function ($schema) use ($validator, $rules) {
                    $name = 'category_field_values.'.$schema->field_key;
                    $field = match ($schema->field_type) {
                        'number' => TextInput::make($name)->numeric(),
                        // A toggle invents false for missing historical values on hydration.
                        'boolean' => Select::make($name)->boolean()->native(),
                        'select' => Select::make($name)->options($validator->optionsFor($schema))->native(),
                        'multiselect' => Select::make($name)->options($validator->optionsFor($schema))->multiple()
                            ->nestedRecursiveRules($rules[$schema->field_key.'.*'] ?? []),
                        'date' => DatePicker::make($name)->native(),
                        default => TextInput::make($name),
                    };

                    return $field->dehydrated()
                        ->label($schema->getTranslation('field_label', app()->getLocale()))
                        ->required(in_array('required', $rules[$schema->field_key] ?? [], true))
                        ->rules($rules[$schema->field_key] ?? []);
                })->all();
            })->columns(2);
    }

    /**
     * Re-hydrate schema-driven fields after a category switch. Filament keeps
     * the component tree cached between Livewire updates, so newly visible
     * fields otherwise have no hydrated state until a second interaction.
     */
    protected static function resetCategorySchemaFields(Set $set, Select $component): void
    {
        $set('category_field_values', []);

        $container = $component->getContainer();

        while ($container->getParentComponent() !== null) {
            $container = $container->getParentComponent()->getContainer();
        }

        $section = $container->getComponent('category-schema-fields', withHidden: true);
        $section?->getChildComponentContainer()->fill();
    }
}

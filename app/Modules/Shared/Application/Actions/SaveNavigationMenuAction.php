<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Enums\NavigationTargetType;
use App\Modules\Shared\Domain\Events\PublicThemeChanged;
use App\Modules\Shared\Domain\Models\NavigationMenu;
use App\Modules\Shared\Domain\Models\NavigationMenuItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveNavigationMenuAction
{
    public function execute(NavigationSlot $slot, string $name, array $items): NavigationMenu
    {
        $validated = $this->validate($items);

        return DB::transaction(function () use ($slot, $name, $validated): NavigationMenu {
            $menu = NavigationMenu::firstOrCreate(
                ['slot' => $slot->value],
                ['name' => $name],
            );

            if ($menu->name !== $name) {
                $menu->name = $name;
                $menu->save();
            }

            $menu->items()->delete();

            $this->insertItems($menu, $validated, parentId: null);

            DB::afterCommit(function () use ($menu): void {
                foreach (['en', 'ar'] as $locale) {
                    Cache::forget('theme:menus:'.$menu->slot->value.':'.$locale);
                }
                event(new PublicThemeChanged(reason: 'menus.saved', publicId: $menu->public_id));
            });

            return $menu->fresh(['items']);
        });
    }

    private function insertItems(NavigationMenu $menu, array $items, ?int $parentId): void
    {
        foreach (array_values($items) as $position => $item) {
            $created = NavigationMenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $parentId,
                'label' => $item['label'],
                'target_type' => $item['target_type'],
                'target_value' => $item['target_value'],
                'icon' => $item['icon'] ?? null,
                'is_visible' => (bool) ($item['is_visible'] ?? true),
                'opens_in_new_tab' => (bool) ($item['opens_in_new_tab'] ?? false),
                'position' => $position,
            ]);

            if ($parentId === null && ! empty($item['children'])) {
                $this->insertItems($menu, $item['children'], $created->id);
            }
        }
    }

    private function validate(array $items): array
    {
        $rules = [
            '*.label.en' => ['required', 'string', 'max:120'],
            '*.label.ar' => ['required', 'string', 'max:120'],
            '*.target_type' => ['required', 'string', 'in:'.implode(',', array_column(NavigationTargetType::cases(), 'value'))],
            '*.target_value' => ['required', 'string', 'max:512'],
            '*.icon' => ['nullable', 'string', 'max:64'],
            '*.is_visible' => ['boolean'],
            '*.opens_in_new_tab' => ['boolean'],
            '*.children' => ['array'],
            '*.children.*.label.en' => ['required', 'string', 'max:120'],
            '*.children.*.label.ar' => ['required', 'string', 'max:120'],
            '*.children.*.target_type' => ['required', 'string', 'in:'.implode(',', array_column(NavigationTargetType::cases(), 'value'))],
            '*.children.*.target_value' => ['required', 'string', 'max:512'],
        ];

        $this->addExternalUrlRules($items, '', $rules);

        $validator = Validator::make($items, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $items;
    }

    /** @param array<string, mixed> $rules */
    private function addExternalUrlRules(array $items, string $prefix, array &$rules): void
    {
        foreach (array_values($items) as $index => $item) {
            $path = $prefix.$index.'.';

            if (($item['target_type'] ?? null) === NavigationTargetType::ExternalUrl->value) {
                $rules[$path.'target_value'][] = static function (string $attribute, mixed $value, \Closure $fail): void {
                    $scheme = is_string($value) ? parse_url($value, PHP_URL_SCHEME) : null;

                    if (! is_string($value)
                        || filter_var($value, FILTER_VALIDATE_URL) === false
                        || ! in_array(strtolower((string) $scheme), ['http', 'https'], true)
                    ) {
                        $fail(__('shared::shared.nav_menu.invalid_destination'));
                    }
                };
            }

            if (is_array($item['children'] ?? null)) {
                $this->addExternalUrlRules($item['children'], $path.'children.', $rules);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Models\CmsPage;
use App\Modules\Shared\Domain\Models\NavigationMenu;
use App\Modules\Shared\Domain\Models\NavigationMenuItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GetNavigationMenuAction
{
    public const CACHE_TTL_SECONDS = 300;

    /** @return array{slot: string, name: string, items: list<array<string, mixed>>} */
    public function execute(NavigationSlot $slot, string $locale): array
    {
        return Cache::remember(
            'theme:menus:'.$slot->value.':'.$locale,
            self::CACHE_TTL_SECONDS,
            function () use ($slot, $locale): array {
                $menu = NavigationMenu::query()
                    ->where('slot', $slot->value)
                    ->with(['items' => fn ($q) => $q->where('is_visible', true)->orderBy('position')])
                    ->first();

                if ($menu === null) {
                    return ['slot' => $slot->value, 'name' => $slot->label(), 'items' => []];
                }

                $cmsPageTargets = $this->cmsPageTargets();

                return [
                    'slot' => $menu->slot->value,
                    'name' => in_array(Str::lower(trim($menu->name)), [
                        $slot->value,
                        str_replace('_', ' ', $slot->value),
                        'footer (primary)',
                        'footer (secondary)',
                        'mobile drawer',
                    ], true) ? $slot->label() : $menu->name,
                    'items' => $this->buildItems($menu, null, $locale, $cmsPageTargets),
                ];
            },
        );
    }

    /** @return list<array<string, mixed>> */
    private function buildItems(NavigationMenu $menu, ?int $parentId, string $locale, array $cmsPageTargets): array
    {
        // ponytail: menus are small; index by parent if a menu grows beyond a few dozen items.
        return array_values($menu->items
            ->where('parent_id', $parentId)
            ->map(fn (NavigationMenuItem $item): array => [
                'public_id' => $item->public_id,
                'label' => $item->getTranslation('label', $locale, useFallbackLocale: true),
                'target_type' => $item->target_type->value,
                'target_value' => $item->target_type->value === 'cms_page'
                    ? ($cmsPageTargets[$item->target_value] ?? $item->target_value)
                    : $item->target_value,
                'icon' => $item->icon,
                'opens_in_new_tab' => $item->opens_in_new_tab,
                'children' => $this->buildItems($menu, $item->id, $locale, $cmsPageTargets),
            ])
            ->values()
            ->all());
    }

    /** @return array<string, string> */
    private function cmsPageTargets(): array
    {
        $targets = [];

        foreach (CmsPage::query()->get(['slug', 'public_slug']) as $page) {
            $target = $page->public_slug ?: $page->slug->value;
            $targets[$page->slug->value] = $target;

            if ($page->public_slug !== null) {
                $targets[$page->public_slug] = $target;
            }
        }

        return $targets;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Forms;

use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Livewire\Component as LivewireComponent;

final class BilingualTabErrorFeedback
{
    public static function tabs(Tabs $tabs): Tabs
    {
        return $tabs
            ->activeTab(fn (LivewireComponent $livewire): int => self::activeTab($livewire))
            ->extraAlpineAttributes([
                'x-init' => <<<'JS'
                    const focusFirstVisibleError = () => {
                        $nextTick(() => {
                            const error = $el.querySelector('.fi-fo-tabs-tab:not(.invisible) [data-validation-error]')
                            const field = error?.closest('[data-field-wrapper]')
                            const control = field?.querySelector('input:not([type="hidden"]), textarea, select, button[role="combobox"]')
                            control?.focus({ preventScroll: true })
                        })
                    }

                    new MutationObserver(focusFirstVisibleError).observe($el, {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['class'],
                    })

                    focusFirstVisibleError()
                    JS,
            ]);
    }

    public static function tab(Tab $tab, string $locale): Tab
    {
        return $tab
            ->badge(function (LivewireComponent $livewire) use ($locale): ?string {
                $count = self::countForLocale($livewire, $locale);

                return $count > 0 ? __('shared::admin-feedback.tab_error_count', ['count' => $count]) : null;
            })
            ->badgeColor('danger');
    }

    public static function countForLocale(LivewireComponent $livewire, string $locale): int
    {
        if (! in_array($locale, ['en', 'ar'], true)) {
            return 0;
        }

        return collect($livewire->getErrorBag()->getMessages())
            ->filter(fn (array $messages, string $path): bool => str_ends_with($path, '.'.$locale))
            ->sum(fn (array $messages): int => count($messages));
    }

    public static function activeTab(LivewireComponent $livewire): int
    {
        if (self::countForLocale($livewire, 'en') > 0) {
            return 1;
        }

        return self::countForLocale($livewire, 'ar') > 0 ? 2 : 1;
    }

    /** @return array<string, string> */
    public static function focusFirstVisibleErrorAttributes(): array
    {
        return [
            'x-init' => <<<'JS'
                const focusFirstVisibleError = () => {
                    $nextTick(() => {
                        const error = $el.querySelector('.fi-fo-tabs-tab:not(.invisible) [data-validation-error]')
                        const field = error?.closest('[data-field-wrapper]')
                        const control = field?.querySelector('input:not([type="hidden"]), textarea, select, button[role="combobox"]')
                        control?.focus({ preventScroll: true })
                    })
                }

                new MutationObserver(focusFirstVisibleError).observe($el, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class'],
                })

                focusFirstVisibleError()
                JS,
        ];
    }
}

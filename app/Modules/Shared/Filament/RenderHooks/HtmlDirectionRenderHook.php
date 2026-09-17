<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\RenderHooks;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\App;

class HtmlDirectionRenderHook
{
    public static function register(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_START,
            fn (): string => self::render(),
        );
    }

    private static function render(): string
    {
        $locale = App::getLocale();
        $dir = $locale === 'ar' ? 'rtl' : 'ltr';

        return "<script>
            document.documentElement.dir='{$dir}';
            document.documentElement.lang='{$locale}';
            document.addEventListener('DOMContentLoaded', () => {
                const syncActiveNavigation = () => {
                    const sidebar = window.Alpine?.store('sidebar');

                    if (window.matchMedia('(max-width: 1023px)').matches && sidebar?.isOpen) sidebar.close();

                    document.querySelectorAll('.fi-sidebar-item-active').forEach((item) => {
                        item.querySelector(':scope > a')?.setAttribute('aria-current', 'page');

                        const group = item.closest('.fi-sidebar-group');
                        const label = group?.dataset.groupLabel;
                        if (label && sidebar?.groupIsCollapsed(label)) sidebar.toggleCollapsedGroup(label);
                        group?.querySelector('.fi-sidebar-group-items')?.style.setProperty('display', 'flex', 'important');
                        group?.querySelector('.fi-sidebar-group-collapse-button')?.setAttribute('aria-expanded', 'true');
                    });
                };

                syncActiveNavigation();
                setTimeout(syncActiveNavigation, 100);
            });
        </script>";
    }
}

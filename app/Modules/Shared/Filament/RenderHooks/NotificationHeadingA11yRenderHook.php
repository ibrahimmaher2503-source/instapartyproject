<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\RenderHooks;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

/**
 * BUG-013 — The database-notifications flyout ships a heading (e.g. the empty
 * "No notifications" state) that can appear before the page <h1> in DOM order,
 * which breaks the heading outline for assistive technology.
 *
 * Filament renders/morphs the flyout client-side, so we demote any heading
 * inside the notifications region to a non-heading status element. The page
 * <h1> is then the first heading encountered in document order.
 */
class NotificationHeadingA11yRenderHook
{
    public static function register(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => self::script(),
        );
    }

    public static function script(): string
    {
        return <<<'HTML'
<script>
(function () {
    // The database-notifications slide-over (id "database-notifications") ships its
    // heading as <h2 class="fi-modal-heading"> and is rendered in the topbar — before
    // the page <h1> in document order, breaking the heading outline for assistive tech.
    // That heading is a flyout label, not part of the page outline, so we demote it to a
    // non-heading element. The modal keeps its accessible name via aria-labelledby (which
    // reads the element's text regardless of role). Scoped by data-fi-modal-id so no other
    // modal/dialog heading is affected.
    function demoteNotificationHeading() {
        document
            .querySelectorAll('[data-fi-modal-id="database-notifications"] .fi-modal-heading')
            .forEach(function (el) {
                if (el.getAttribute('data-a11y-demoted')) { return; }
                el.setAttribute('role', 'presentation');
                el.setAttribute('data-a11y-demoted', '1');
            });
    }
    document.addEventListener('DOMContentLoaded', demoteNotificationHeading);
    document.addEventListener('livewire:navigated', demoteNotificationHeading);
    document.addEventListener('livewire:update', demoteNotificationHeading);
    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(demoteNotificationHeading)
            .observe(document.documentElement, { childList: true, subtree: true });
    }
})();
</script>
HTML;
    }
}

<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Actions\GetNavigationMenuAction;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Models\NavigationMenu;

it('replaces legacy footer placeholders with localized headings', function (): void {
    NavigationMenu::query()->create([
        'slot' => NavigationSlot::FooterPrimary,
        'name' => 'Footer primary',
    ]);

    app()->setLocale('ar');

    $menu = app(GetNavigationMenuAction::class)->execute(NavigationSlot::FooterPrimary, 'ar');

    expect($menu['name'])->toBe('استكشف');
});

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum NavigationSlot: string
{
    case Header = 'header';
    case FooterPrimary = 'footer_primary';
    case FooterSecondary = 'footer_secondary';
    case MobileDrawer = 'mobile_drawer';

    public function label(): string
    {
        return __("shared.navigation_slots.{$this->value}");
    }
}

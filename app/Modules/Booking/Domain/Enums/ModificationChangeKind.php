<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum ModificationChangeKind: string
{
    case Add = 'add';
    case Remove = 'remove';
    case Update = 'update';

    public function label(): string
    {
        return match ($this) {
            self::Add => 'Add item',
            self::Remove => 'Remove item',
            self::Update => 'Update item',
        };
    }
}

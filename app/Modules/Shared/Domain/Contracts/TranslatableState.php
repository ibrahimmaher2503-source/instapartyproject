<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

interface TranslatableState
{
    /**
     * Return a locale-resolved label for a raw state name, or null when unknown.
     */
    public static function label(string $stateName, string $locale): ?string;
}

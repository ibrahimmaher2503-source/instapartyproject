<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

final readonly class OccasionCategoriesChanged
{
    /** @param list<int> $categoryIds */
    public function __construct(public array $categoryIds) {}
}

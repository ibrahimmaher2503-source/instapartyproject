<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;

class StorePlannerContextAction
{
    /** @param array<string, mixed> $context */
    public function execute(array $context): string
    {
        Session::put('storefront.planner_notes', $context['notes'] ?? null);

        return route('storefront.search', ['planner' => 1, ...Arr::except($context, ['notes'])]);
    }
}

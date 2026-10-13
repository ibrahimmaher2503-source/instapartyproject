<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;

class StorePlannerContextAction
{
    /** @param array<string, mixed> $context */
    public function execute(array $context): string
    {
        Session::put('storefront.planner_notes', $context['notes'] ?? null);
        $timezone = $context['timezone'] ?? config('app.timezone', 'UTC');
        $startsAt = CarbonImmutable::parse((string) $context['event_starts_at'], (string) $timezone);
        $context['event_date'] = $startsAt->setTimezone((string) $timezone)->toDateString();
        $context['event_starts_at'] = $startsAt->utc()->toIso8601String();

        if (isset($context['event_ends_at'])) {
            $context['event_ends_at'] = CarbonImmutable::parse((string) $context['event_ends_at'], (string) $timezone)->utc()->toIso8601String();
        }

        return route('storefront.search', ['planner' => 1, ...Arr::except($context, ['notes', 'timezone'])]);
    }
}

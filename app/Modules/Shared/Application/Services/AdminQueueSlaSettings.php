<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Shared\Domain\Enums\AdminQueue;
use App\Modules\Shared\Domain\Models\AppSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AdminQueueSlaSettings
{
    public const DEFAULT_HOURS = 48;

    public const MIN_HOURS = 1;

    public const MAX_HOURS = 720;

    public function hours(AdminQueue $queue): int
    {
        $value = AppSetting::query()->where('key', $queue->settingKey())->first()?->value;

        if (! is_int($value) && (! is_string($value) || preg_match('/^\d+$/D', $value) !== 1)) {
            return self::DEFAULT_HOURS;
        }

        $hours = (int) $value;

        return $hours >= self::MIN_HOURS && $hours <= self::MAX_HOURS
            ? $hours
            : self::DEFAULT_HOURS;
    }

    public function deadlineFor(AdminQueue $queue, CarbonInterface $startedAt): CarbonImmutable
    {
        return CarbonImmutable::instance($startedAt)->utc()->addHours($this->hours($queue));
    }

    public function isOverdue(
        AdminQueue $queue,
        CarbonInterface $startedAt,
        ?CarbonInterface $now = null,
    ): bool {
        return ($now === null ? CarbonImmutable::now('UTC') : CarbonImmutable::instance($now)->utc())
            ->greaterThanOrEqualTo($this->deadlineFor($queue, $startedAt));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline\DTOs;

use App\Modules\Shared\Application\Timeline\Enums\TimelineActorRole;
use App\Modules\Shared\Application\Timeline\Enums\TimelineEventKind;

final readonly class TimelineFilters
{
    public function __construct(
        public ?TimelineEventKind $eventKind = null,
        public ?TimelineActorRole $actorRole = null,
    ) {}
}

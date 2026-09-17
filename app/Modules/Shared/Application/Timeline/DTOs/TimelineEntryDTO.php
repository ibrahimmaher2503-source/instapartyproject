<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline\DTOs;

use App\Modules\Shared\Application\Timeline\Enums\TimelineActorRole;
use App\Modules\Shared\Application\Timeline\Enums\TimelineEventKind;
use Carbon\CarbonImmutable;

final readonly class TimelineEntryDTO
{
    public function __construct(
        public CarbonImmutable $occurredAt,       // UTC
        public string $sourceTable,      // e.g. 'state_transitions'
        public ?string $sourcePublicId,   // ULID when source has one
        public string $actorLabel,       // already locale-resolved
        public TimelineActorRole $actorRole,
        public string $actionKey,        // e.g. 'vendor.approved_for_rental'
        public ?string $fromState,        // raw state name, view layer translates
        public ?string $toState,          // raw state name
        public ?string $note,             // already locale-resolved
        public TimelineEventKind $eventKind,
        public bool $isAdminOnly,
        public array $extra,            // assoc array, audience-filtered
    ) {}
}

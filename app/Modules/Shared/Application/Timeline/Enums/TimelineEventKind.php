<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline\Enums;

enum TimelineEventKind: string
{
    case StateChange = 'state_change';
    case Financial = 'financial';
    case Moderation = 'moderation';
    case Note = 'note';
    case Document = 'document';
    case System = 'system';
}

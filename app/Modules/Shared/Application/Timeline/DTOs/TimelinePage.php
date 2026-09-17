<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline\DTOs;

final readonly class TimelinePage
{
    /**
     * @param  array<int, TimelineEntryDTO>  $entries
     */
    public function __construct(
        public array $entries,
        public ?TimelineCursor $nextCursor,
        public TimelineMeta $meta,
    ) {}
}

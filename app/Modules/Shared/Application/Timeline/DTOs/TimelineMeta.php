<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline\DTOs;

final readonly class TimelineMeta
{
    public function __construct(
        public int $pageSize,
        public bool $hasMore,
        public ?int $legacyLedgerOmittedCount = null, // admin-only; null for vendor by construction
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

final readonly class ReconcileWalletResult
{
    /** @param array<int, string|null> $findingResolutions Finding IDs and their current resolution. */
    public function __construct(
        public int $walletId,
        public int $findingsCount,
        public int $autoRepairedCount,
        public int $manualReviewCount,
        public array $findingResolutions = [],
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

final readonly class TestPushDTO
{
    public function __construct(
        public string $deviceToken,
        public int $adminUserId,
        public ?string $bodyOverride = null,
    ) {}
}

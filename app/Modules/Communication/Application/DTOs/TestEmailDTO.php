<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

final readonly class TestEmailDTO
{
    public function __construct(
        public string $emailAddress,
        public int $adminUserId,
        public ?string $subjectOverride = null,
        public ?string $bodyOverride = null,
    ) {}
}

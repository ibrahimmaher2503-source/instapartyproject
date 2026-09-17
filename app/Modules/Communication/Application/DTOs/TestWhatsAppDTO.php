<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

final readonly class TestWhatsAppDTO
{
    public function __construct(
        public string $phoneE164,
        public int $adminUserId,
        public ?string $bodyOverride = null,
    ) {}
}

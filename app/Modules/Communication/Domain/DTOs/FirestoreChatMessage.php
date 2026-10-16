<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\DTOs;

use Illuminate\Support\Carbon;

final readonly class FirestoreChatMessage
{
    public function __construct(
        public string $id,
        public string $senderUserId,
        public ?string $body,
        public Carbon $createdAt,
        public bool $blocked,
        public bool $redacted,
    ) {}
}

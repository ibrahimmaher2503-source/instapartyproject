<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\DTOs;

final readonly class FirestoreChatMessagePage
{
    /** @param list<FirestoreChatMessage> $messages */
    public function __construct(
        public array $messages,
        public ?string $nextPageToken,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

final class MirrorFirestoreMessageDTO
{
    /**
     * @param  list<array{flag_type:string,matched_pattern:string}>  $matchedPatterns
     */
    public function __construct(
        public readonly string $firestoreThreadId,
        public readonly string $firestoreMessageId,
        public readonly string $senderUserId,
        public readonly string $body,
        public readonly bool $blocked,
        public readonly ?string $flagReason,
        public readonly array $matchedPatterns,
    ) {}
}

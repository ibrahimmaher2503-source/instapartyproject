<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired AFTER an `audit_logs` row has been written for a signed-URL generation
 * against `s3-private` media. Payload is scalar only (per .claude/rules/actions.md
 * event-payload rule, fix commit ff6ce5e) so queued listeners can deserialize cleanly.
 *
 * Per FR-EXT-MED-008 and ADR-0047 §3.
 */
final class PrivateMediaAccessed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $mediaId,
        public readonly string $mediaPublicId,
        public readonly string $owningEntityType,
        public readonly int $owningEntityId,
        public readonly int $accessorUserId,
        public readonly string $ip,
        public readonly ?string $userAgent,
        public readonly int $ttlSeconds,
    ) {}
}

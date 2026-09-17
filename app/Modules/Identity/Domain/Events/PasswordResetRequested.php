<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a password-reset request is recorded. Payload is strictly
 * scalar so queued listeners can serialize it without the silent failures
 * documented in actions.md (fix commit ff6ce5e).
 *
 * The `tokenPlain` value is the cleartext token to deliver via email/SMS;
 * the corresponding hash is what is persisted in `password_reset_tokens`.
 * Listeners should not re-hash it.
 */
class PasswordResetRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $identifier,
        public readonly string $channel,
        public readonly string $tokenPlain,
        public readonly string $locale,
    ) {}
}

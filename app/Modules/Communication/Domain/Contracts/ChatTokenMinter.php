<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

/**
 * Mints a Firebase custom token for the chat identity bridge (spec 056).
 *
 * uid is always the numeric users.id as a string (matches
 * chat_threads.customer_id / vendor user id mirrored into Firestore
 * participantUserIds), with a role=admin custom claim for admins —
 * identical contract to the mintChatToken Cloud Function used by the
 * Next.js proxy path.
 */
interface ChatTokenMinter
{
    public function mint(int $userId, bool $isAdmin): string;
}

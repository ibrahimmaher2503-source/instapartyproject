<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Communication\Domain\Events\ChatThreadFrozen;
use App\Modules\Communication\Domain\Events\ChatThreadUnfrozen;

/**
 * Propagates admin freeze/unfreeze decisions to the Firestore thread document so
 * the transport-side rule (no sends while frozen) is enforced immediately
 * (FR-EXT-056-009). Runs synchronously after the moderation action commits.
 */
final class PushChatFreezeToFirestoreListener
{
    public function __construct(
        private readonly FirestoreChatGateway $gateway,
    ) {}

    public function handle(ChatThreadFrozen|ChatThreadUnfrozen $event): void
    {
        $firestoreThreadId = (string) ($event->thread->firestore_thread_id ?? '');
        if ($firestoreThreadId === '') {
            return;
        }

        if ($event instanceof ChatThreadFrozen) {
            $this->gateway->freezeThread($firestoreThreadId);
        } else {
            $this->gateway->unfreezeThread($firestoreThreadId);
        }
    }
}

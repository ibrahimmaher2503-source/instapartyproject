<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

use App\Modules\Communication\Domain\DTOs\FirestoreChatMessagePage;

interface FirestoreChatGateway
{
    /**
     * Provision the Firestore thread document with participant metadata.
     * Idempotent (merge write). Called when a booking is confirmed.
     */
    public function createThread(
        string $firestoreThreadId,
        string $bookingPublicId,
        string $customerUserId,
        string $vendorUserId,
    ): void;

    public function freezeThread(string $firestoreThreadId): void;

    public function unfreezeThread(string $firestoreThreadId): void;

    /**
     * Write a message to Firestore and return the Firestore message document ID.
     */
    public function sendMessage(string $firestoreThreadId, string $senderUserId, string $body, ?string $messageId = null): string;

    /** Read one bounded page, newest messages first. */
    public function listMessages(string $firestoreThreadId, ?string $pageToken = null): FirestoreChatMessagePage;
}

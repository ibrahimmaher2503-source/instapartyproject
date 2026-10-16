<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Communication\Domain\DTOs\FirestoreChatMessagePage;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class FirestoreChatGatewayStub implements FirestoreChatGateway
{
    public function createThread(
        string $firestoreThreadId,
        string $bookingPublicId,
        string $customerUserId,
        string $vendorUserId,
    ): void {
        Log::info('FirestoreChatGateway::createThread', ['thread_id' => $firestoreThreadId, 'booking' => $bookingPublicId]);
    }

    public function freezeThread(string $firestoreThreadId): void
    {
        Log::info('FirestoreChatGateway::freezeThread', ['thread_id' => $firestoreThreadId]);
    }

    public function unfreezeThread(string $firestoreThreadId): void
    {
        Log::info('FirestoreChatGateway::unfreezeThread', ['thread_id' => $firestoreThreadId]);
    }

    public function sendMessage(string $firestoreThreadId, string $senderUserId, string $body, ?string $messageId = null): string
    {
        throw new RuntimeException('FIRESTORE_CHAT_NOT_CONFIGURED');
    }

    public function listMessages(string $firestoreThreadId, ?string $pageToken = null): FirestoreChatMessagePage
    {
        throw new RuntimeException('FIRESTORE_CHAT_NOT_CONFIGURED');
    }
}

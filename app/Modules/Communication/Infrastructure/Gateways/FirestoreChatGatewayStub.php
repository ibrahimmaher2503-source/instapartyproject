<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class FirestoreChatGatewayStub implements FirestoreChatGateway
{
    public function createThread(
        string $firestoreThreadId,
        string $bookingPublicId,
        string $customerUserId,
        string $vendorUserId,
    ): void {
        Log::info('FirestoreChatGateway::createThread', [
            'thread_id' => $firestoreThreadId,
            'booking' => $bookingPublicId,
        ]);
    }

    public function freezeThread(string $firestoreThreadId): void
    {
        Log::info('FirestoreChatGateway::freezeThread', ['thread_id' => $firestoreThreadId]);
    }

    public function unfreezeThread(string $firestoreThreadId): void
    {
        Log::info('FirestoreChatGateway::unfreezeThread', ['thread_id' => $firestoreThreadId]);
    }

    public function sendMessage(string $firestoreThreadId, string $senderUserId, string $body): string
    {
        Log::info('FirestoreChatGateway::sendMessage', [
            'thread_id' => $firestoreThreadId,
            'sender' => $senderUserId,
        ]);

        return 'stub_msg_'.Str::uuid()->toString();
    }
}

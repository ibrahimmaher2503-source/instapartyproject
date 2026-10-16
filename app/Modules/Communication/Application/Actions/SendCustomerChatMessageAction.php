<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\Services\ChatPanelStateResolver;
use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Communication\Domain\Enums\ChatPanelState;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Infrastructure\Services\MessagePatternDetector;
use DomainException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class SendCustomerChatMessageAction
{
    public function __construct(
        private readonly ChatPanelStateResolver $stateResolver,
        private readonly MessagePatternDetector $detector,
        private readonly FirestoreChatGateway $gateway,
        private readonly CacheRepository $cache,
    ) {}

    public function execute(ChatThread $thread, int $customerUserId, string $body): string
    {
        if ((int) $thread->customer_id !== $customerUserId) {
            throw new DomainException('Customer does not own this chat thread.');
        }

        if ($this->stateResolver->resolve($thread, $thread->booking) !== ChatPanelState::Open) {
            throw new DomainException('Chat thread is not open for sending.');
        }

        if ($this->detector->detect($body) !== []) {
            throw new DomainException('MESSAGE_BLOCKED');
        }

        $key = sprintf('chat_dedup:%d:%d:%s', $thread->id, $customerUserId, md5($body));
        if ($this->cache->has($key)) {
            throw new DomainException('Duplicate message submission detected.');
        }
        $this->cache->put($key, true, 10);

        try {
            return $this->gateway->sendMessage(
                $thread->firestore_thread_id ?? '',
                (string) $customerUserId,
                $body,
                Str::ulid()->toBase32(),
            );
        } catch (Throwable $exception) {
            Log::warning('communication.booking_chat.send_failed', [
                'thread_id' => $thread->id,
                'error_class' => class_basename($exception),
            ]);

            throw new RuntimeException('FIRESTORE_CHAT_DELIVERY_FAILED', previous: $exception);
        }
    }
}

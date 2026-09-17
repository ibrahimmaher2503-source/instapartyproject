<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\MirrorFirestoreMessageDTO;
use App\Modules\Communication\Application\Jobs\DetectSuspiciousMessageJob;
use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Domain\Enums\ChatMessageFlagReason;
use App\Modules\Communication\Domain\Events\ChatFlagged;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Domain\Models\ChatThread;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mirrors a Firestore message (clean or already-redacted by the Cloud Function)
 * into the relational store. Idempotent on chat_message_log.firestore_message_id
 * (FR-EXT-056-007 / SC-005). Records moderation flags + audit row when blocked,
 * and dispatches a secondary detection sweep for clean messages.
 */
final class MirrorFirestoreMessageAction
{
    /**
     * @throws DomainException When the thread is unknown.
     */
    public function execute(MirrorFirestoreMessageDTO $dto): ChatMessageLog
    {
        $thread = ChatThread::query()
            ->where('firestore_thread_id', $dto->firestoreThreadId)
            ->first();

        if ($thread === null) {
            throw new DomainException('Unknown chat thread for firestore_thread_id: '.$dto->firestoreThreadId);
        }

        $existing = ChatMessageLog::query()
            ->where('firestore_message_id', $dto->firestoreMessageId)
            ->first();

        if ($existing !== null) {
            return $existing; // idempotent replay
        }

        return DB::transaction(function () use ($thread, $dto): ChatMessageLog {
            $log = $this->createLog($thread, $dto);

            if ($dto->blocked) {
                $this->createFlags($log, $dto->matchedPatterns);
                $this->writeAuditRow($thread, $log, (int) $dto->senderUserId, 'chat.message.blocked');

                DB::afterCommit(static function () use ($thread): void {
                    event(new ChatFlagged($thread->id));
                });
            } else {
                $this->writeAuditRow($thread, $log, (int) $dto->senderUserId, 'chat.message.sent');

                // Secondary sweep — catches anything the transport-side detector missed.
                $logId = $log->id;
                $body = $dto->body;
                DB::afterCommit(static function () use ($logId, $body): void {
                    DetectSuspiciousMessageJob::dispatchWithBody($logId, $body);
                });
            }

            return $log;
        });
    }

    private function createLog(ChatThread $thread, MirrorFirestoreMessageDTO $dto): ChatMessageLog
    {
        $log = new ChatMessageLog;
        $log->public_id = Str::ulid()->toBase32();
        $log->chat_thread_id = $thread->id;
        $log->sender_id = (int) $dto->senderUserId;
        $log->firestore_message_id = $dto->firestoreMessageId;
        $log->message_kind = 'text';
        $log->detected_locale = $this->detectLocale($dto->body);
        $log->body = $dto->body;
        $log->flagged = $dto->blocked;
        $log->flag_reason = $dto->blocked ? $this->mapReason($dto->flagReason)?->value : null;
        $log->redacted = $dto->blocked;
        $log->created_at = now();
        $log->save();

        return $log;
    }

    /**
     * @param  list<array{flag_type:string,matched_pattern:string}>  $patterns
     */
    private function createFlags(ChatMessageLog $log, array $patterns): void
    {
        foreach ($patterns as $pattern) {
            $flagType = ChatFlagType::tryFrom($pattern['flag_type'] ?? '');
            if ($flagType === null) {
                continue;
            }

            ChatModerationFlag::firstOrCreate(
                [
                    'chat_message_log_id' => $log->id,
                    'flag_type' => $flagType,
                ],
                [
                    'matched_pattern' => mb_substr((string) ($pattern['matched_pattern'] ?? ''), 0, 255),
                    'action_taken' => ChatFlagAction::Block,
                ],
            );
        }
    }

    private function writeAuditRow(ChatThread $thread, ChatMessageLog $log, int $senderUserId, string $action): void
    {
        DB::table('audit_logs')->insert([
            'public_id' => Str::ulid()->toBase32(),
            'auditable_type' => ChatThread::class,
            'auditable_id' => $thread->id,
            'user_id' => $senderUserId,
            'action' => $action,
            'changes' => json_encode(['chat_message_log_id' => $log->id], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }

    private function mapReason(?string $flagReason): ?ChatMessageFlagReason
    {
        return match ($flagReason) {
            'phone' => ChatMessageFlagReason::PhonePattern,
            'email' => ChatMessageFlagReason::EmailPattern,
            'external_link' => ChatMessageFlagReason::ExternalLink,
            default => null,
        };
    }

    private function detectLocale(string $body): string
    {
        return preg_match('/\p{Arabic}/u', $body) === 1 ? 'ar' : 'en';
    }
}

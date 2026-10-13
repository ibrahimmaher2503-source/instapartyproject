<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Domain\Models\ChatThread;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class RecordChatModerationReadAction
{
    public function execute(ChatThread|ChatModerationFlag|ChatMessageLog $record, ?int $actorId): void
    {
        abort_unless(auth()->id() === $actorId && auth()->user()?->can('chat_moderation.view') === true, 403);
        $threadAccess = $record instanceof ChatThread;

        DB::table('audit_logs')->insert([
            'public_id' => (string) Str::ulid(),
            'auditable_type' => $record::class,
            'auditable_id' => $record->getKey(),
            'user_id' => $actorId,
            'action' => match (true) {
                $record instanceof ChatThread => 'chat_thread_viewed',
                $record instanceof ChatMessageLog => 'chat_message_viewed',
                default => 'chat_moderation_flag_viewed',
            },
            'changes' => json_encode([
                'access' => $threadAccess ? 'moderation_detail' : 'moderation_flag_detail',
            ], JSON_THROW_ON_ERROR),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Modules\Communication\Database\Factories\ChatModerationFlagFactory;
use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property ChatFlagType|null $flag_type
 * @property string|null $matched_pattern
 * @property ChatFlagAction|null $action_taken
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property-read ChatMessageLog|null $messageLog
 */
class ChatModerationFlag extends Model
{
    /** @use HasFactory<ChatModerationFlagFactory> */
    use HasFactory;

    protected $table = 'chat_moderation_flags';

    public $timestamps = false;

    protected static function newFactory(): ChatModerationFlagFactory
    {
        return ChatModerationFlagFactory::new();
    }

    protected $fillable = [
        'public_id',
        'chat_message_log_id',
        'flag_type',
        'matched_pattern',
        'action_taken',
        'reviewed_by',
        'reviewed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $flag): void {
            if (empty($flag->public_id)) {
                $flag->public_id = Str::ulid()->toBase32();
            }
        });
    }

    protected $casts = [
        'flag_type' => ChatFlagType::class,
        'action_taken' => ChatFlagAction::class,
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<ChatMessageLog, $this> */
    public function messageLog(): BelongsTo
    {
        return $this->belongsTo(ChatMessageLog::class, 'chat_message_log_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function thread(): HasOneThrough
    {
        return $this->hasOneThrough(
            ChatThread::class,
            ChatMessageLog::class,
            'id',                 // ChatMessageLog primary key
            'id',                 // ChatThread primary key
            'chat_message_log_id', // FK on ChatModerationFlag
            'chat_thread_id',     // FK on ChatMessageLog
        );
    }
}

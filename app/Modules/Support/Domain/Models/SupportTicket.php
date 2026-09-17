<?php

declare(strict_types=1);

namespace App\Modules\Support\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Support\Domain\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int|null $user_id
 * @property string|null $email
 * @property string $subject
 * @property string $body
 * @property SupportTicketStatus $status
 * @property int|null $booking_id
 * @property int|null $assigned_to
 */
class SupportTicket extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id', 'user_id', 'email', 'subject', 'body', 'status', 'booking_id', 'assigned_to',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatus::class,
            'user_id' => 'integer',
            'booking_id' => 'integer',
            'assigned_to' => 'integer',
        ];
    }

    /** @phpstan-return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Identity\Domain\Models\User');
    }

    /** @phpstan-return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Identity\Domain\Models\User', 'assigned_to');
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }
}

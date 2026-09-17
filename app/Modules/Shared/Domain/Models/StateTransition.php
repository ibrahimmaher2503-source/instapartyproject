<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Database\Factories\StateTransitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property string|null $from_state
 * @property string $to_state
 * @property string|null $trace_id
 */
class StateTransition extends Model
{
    /** @use HasFactory<StateTransitionFactory> */
    use HasFactory;

    protected $table = 'state_transitions';

    protected static function newFactory(): StateTransitionFactory
    {
        return StateTransitionFactory::new();
    }

    public const UPDATED_AT = null;

    protected $fillable = [
        'transitionable_type',
        'transitionable_id',
        'from_state',
        'to_state',
        'triggered_by',
        'trigger_kind',
        'reason',
        'trace_id',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $transition): void {
            if ($transition->from_state !== null && $transition->from_state === $transition->to_state) {
                throw new LogicException('A state transition must change state.');
            }

            if (blank($transition->trace_id) && app()->bound('request')) {
                $header = request()->header('X-Correlation-ID') ?? request()->header('X-Request-ID');
                $transition->trace_id = is_string($header) && strlen($header) <= 36 ? $header : null;
            }

            $transition->trace_id ??= (string) Str::uuid();
        });
    }

    /** @return MorphTo<Model, $this> */
    public function transitionable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}

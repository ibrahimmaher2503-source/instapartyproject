<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $public_id
 * @property int $service_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property array<string, string>|null $reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ServiceAvailabilityBlock extends Model
{
    use HasPublicId, HasTranslations;

    public array $translatable = ['reason'];

    protected $fillable = [
        'public_id',
        'service_id',
        'starts_at',
        'ends_at',
        'reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @param Builder<ServiceAvailabilityBlock> $query */
    public function scopeOverlapping(Builder $query, Carbon $startsAt, Carbon $endsAt): Builder
    {
        return $query
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);
    }
}

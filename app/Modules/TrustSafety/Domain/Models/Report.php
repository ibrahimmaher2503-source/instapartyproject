<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\TrustSafety\Domain\Enums\ReportReason;
use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $public_id
 * @property int $reporter_id
 * @property string $reportable_type
 * @property int $reportable_id
 * @property ReportReason $reason
 * @property string|null $details
 * @property ReportStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 */
class Report extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id', 'reporter_id', 'reportable_type', 'reportable_id',
        'reason', 'details', 'status', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return MorphTo<Model, $this> */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @param Builder<Report> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', ReportStatus::Open->value);
    }
}

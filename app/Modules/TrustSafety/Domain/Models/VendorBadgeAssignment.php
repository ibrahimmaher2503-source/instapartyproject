<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $vendor_profile_id
 * @property int $trust_badge_id
 * @property int $assigned_by
 * @property Carbon $assigned_at
 */
class VendorBadgeAssignment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'vendor_profile_id', 'trust_badge_id', 'assigned_by', 'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TrustBadge, $this> */
    public function trustBadge(): BelongsTo
    {
        return $this->belongsTo(TrustBadge::class);
    }
}

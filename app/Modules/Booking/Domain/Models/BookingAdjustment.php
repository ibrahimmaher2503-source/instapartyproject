<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAdjustment extends Model
{
    use HasPublicId;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $table = 'booking_adjustments';

    protected $fillable = [
        'public_id', 'booking_id', 'type', 'amount_minor', 'currency',
        'source', 'source_reference', 'created_by_user_id', 'created_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}

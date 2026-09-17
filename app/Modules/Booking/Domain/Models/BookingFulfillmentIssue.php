<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Database\Factories\BookingFulfillmentIssueFactory;
use App\Modules\Booking\Domain\Enums\FulfillmentIssueReason;
use App\Modules\Booking\Domain\Enums\FulfillmentIssueStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $public_id
 * @property int $booking_vendor_id
 * @property int|null $booking_item_id
 * @property int $reported_by_user_id
 * @property FulfillmentIssueReason $reason_code
 * @property string $note
 * @property FulfillmentIssueStatus $status
 * @property int|null $acknowledged_by_admin_user_id
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BookingFulfillmentIssue extends Model implements HasMedia
{
    use HasFactory;
    use HasPublicId;
    use InteractsWithMedia;

    protected $fillable = [
        'public_id',
        'booking_vendor_id',
        'booking_item_id',
        'reported_by_user_id',
        'reason_code',
        'note',
        'status',
        'acknowledged_by_admin_user_id',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'reason_code' => FulfillmentIssueReason::class,
        'status' => FulfillmentIssueStatus::class,
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function newFactory(): BookingFulfillmentIssueFactory
    {
        return BookingFulfillmentIssueFactory::new();
    }

    public function bookingVendor(): BelongsTo
    {
        return $this->belongsTo(BookingVendor::class);
    }

    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_admin_user_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('issue_evidence')
            ->useDisk('s3-private')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }
}

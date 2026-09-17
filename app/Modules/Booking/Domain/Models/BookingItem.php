<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Database\Factories\BookingItemFactory;
use App\Modules\Booking\Domain\States\DigitalItemStatus\DigitalItemStatus;
use App\Modules\Booking\Domain\States\RentalItemStatus\RentalItemStatus;
use App\Modules\Booking\Domain\States\SaleItemStatus\SaleItemStatus;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelStates\State;

/**
 * @property ProductType $product_type
 * @property array<string,string> $name_snapshot
 * @property string $public_id
 * @property int $unit_price_minor
 * @property string $unit_price_currency
 * @property Carbon|null $effective_starts_at
 * @property Carbon|null $effective_ends_at
 * @property int $line_total_minor
 * @property string $line_total_currency
 * @property int $commission_minor
 * @property string $commission_currency
 * @property string $item_status
 * @property int $quantity
 * @property int $commission_bps
 * @property int $booking_vendor_id
 * @property string|null $completion_note
 * @property int|null $completion_photo_media_id
 * @property Carbon|null $completed_at
 * @property int|null $completed_by_vendor_user_id
 */
class BookingItem extends Model implements HasMedia
{
    use HasFactory;
    use HasPublicId;
    use InteractsWithMedia;

    protected $fillable = [
        'public_id', 'booking_vendor_id', 'service_id', 'product_type',
        'name_snapshot',
        'unit_price_minor', 'unit_price_currency',
        'line_total_minor', 'line_total_currency',
        'commission_minor', 'commission_currency',
        'quantity', 'effective_starts_at', 'effective_ends_at',
        'has_item_slot_override', 'customization_data', 'type_snapshot',
        'fulfillment_data', 'item_status', 'commission_bps',
        'vat_rate_bps', 'vat_amount_minor', 'vat_amount_currency',
        'completion_note', 'completion_photo_media_id', 'completed_at', 'completed_by_vendor_user_id',
    ];

    protected $casts = [
        'product_type' => ProductType::class,
        'name_snapshot' => 'array',
        'type_snapshot' => 'array',
        'customization_data' => 'array',
        'fulfillment_data' => 'array',
        'effective_starts_at' => 'datetime',
        'effective_ends_at' => 'datetime',
        'has_item_slot_override' => 'boolean',
        'quantity' => 'integer',
        'commission_bps' => 'integer',
        'vat_rate_bps' => 'integer',
        'vat_amount_minor' => 'integer',
        'completed_at' => 'datetime',
        'unit_price' => MoneyCast::class.':unit_price',
        'line_total' => MoneyCast::class.':line_total',
        'commission' => MoneyCast::class.':commission',
    ];

    protected static function newFactory(): BookingItemFactory
    {
        return BookingItemFactory::new();
    }

    public function bookingVendor(): BelongsTo
    {
        return $this->belongsTo(BookingVendor::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function completionPhoto(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'completion_photo_media_id');
    }

    public function completedByVendorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_vendor_user_id');
    }

    public function fulfillmentIssues(): HasMany
    {
        return $this->hasMany(BookingFulfillmentIssue::class);
    }

    public function resolveItemState(): State
    {
        return match ($this->product_type) {
            ProductType::Rental => RentalItemStatus::make($this->item_status, $this)->setField('item_status'),
            ProductType::Sale => SaleItemStatus::make($this->item_status, $this)->setField('item_status'),
            ProductType::Digital => DigitalItemStatus::make($this->item_status, $this)->setField('item_status'),
        };
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('completion_evidence')
            ->useDisk('s3-private')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        // G8 — rental handover/return condition photos (multi-file).
        $this->addMediaCollection('condition_photos')
            ->useDisk('s3-private')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }
}

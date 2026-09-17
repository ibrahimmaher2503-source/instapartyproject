<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\States\DigitalItemStatus\PendingState as DigitalPendingState;
use App\Modules\Booking\Domain\States\DigitalItemStatus\SentState;
use App\Modules\Booking\Domain\States\RentalItemStatus\PendingDeliveryState;
use App\Modules\Booking\Domain\States\RentalItemStatus\PickedUpState;
use App\Modules\Booking\Domain\States\SaleItemStatus\DeliveredState as SaleDeliveredState;
use App\Modules\Booking\Domain\States\SaleItemStatus\PendingState as SalePendingState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $booking_id
 * @property Booking|null $booking
 * @property int $vendor_profile_id
 * @property VendorSubStatus $sub_status
 * @property Carbon|null $response_deadline
 * @property Carbon|null $responded_at
 * @property int $subtotal_minor
 * @property string $subtotal_currency
 * @property int $delivery_fee_minor
 * @property string $delivery_fee_currency
 * @property int $commission_minor
 * @property string $commission_currency
 * @property int $vendor_payout_minor
 * @property string $vendor_payout_currency
 * @property array<string,string>|null $rejection_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Collection<int, BookingItem> $items
 */
class BookingVendor extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'public_id', 'booking_id', 'vendor_profile_id',
        'sub_status', 'response_deadline', 'responded_at',
        'rejection_reason', 'vendor_notes',
        'subtotal_minor', 'subtotal_currency',
        'delivery_fee_minor', 'delivery_fee_currency',
        'commission_minor', 'commission_currency',
        'vendor_payout_minor', 'vendor_payout_currency',
    ];

    protected $casts = [
        'sub_status' => VendorSubStatus::class,
        'response_deadline' => 'datetime',
        'responded_at' => 'datetime',
        'rejection_reason' => 'array',
        'vendor_notes' => 'array',
        'subtotal' => MoneyCast::class.':subtotal',
        'delivery_fee' => MoneyCast::class.':delivery_fee',
        'commission' => MoneyCast::class.':commission',
        'vendor_payout' => MoneyCast::class.':vendor_payout',
    ];

    protected static function newFactory(): BookingVendorFactory
    {
        return BookingVendorFactory::new();
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    /** @return HasMany<BookingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /** @return HasMany<BookingModification, $this> */
    public function modifications(): HasMany
    {
        return $this->hasMany(BookingModification::class);
    }

    /** @return HasMany<BookingFulfillmentIssue, $this> */
    public function fulfillmentIssues(): HasMany
    {
        return $this->hasMany(BookingFulfillmentIssue::class);
    }

    public function allItemsAtVendorTerminalState(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        if ($items->isEmpty()) {
            return false;
        }

        return $items->every(fn (BookingItem $item) => match ($item->product_type) {
            ProductType::Rental => $item->item_status === PickedUpState::$name,
            ProductType::Sale => $item->item_status === SaleDeliveredState::$name,
            ProductType::Digital => $item->item_status === SentState::$name,
        });
    }

    public function hasAnyItemAdvancedFromInitial(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->contains(fn (BookingItem $item) => match ($item->product_type) {
            ProductType::Rental => $item->item_status !== PendingDeliveryState::$name,
            ProductType::Sale => $item->item_status !== SalePendingState::$name,
            ProductType::Digital => $item->item_status !== DigitalPendingState::$name,
        });
    }
}

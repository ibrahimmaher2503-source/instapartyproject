<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceInventoryReservationFactory;
use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceInventoryReservation extends Model
{
    /** @use HasFactory<ServiceInventoryReservationFactory> */
    use HasFactory;

    use HasPublicId;

    protected static function newFactory(): ServiceInventoryReservationFactory
    {
        return ServiceInventoryReservationFactory::new();
    }

    protected $fillable = [
        'public_id',
        'service_id',
        'user_id',
        'product_type',
        'hold_type',
        'status',
        'reserved_starts_at',
        'reserved_ends_at',
        'quantity',
        'expires_at',
        'booking_item_id',
    ];

    protected $casts = [
        'product_type' => ProductType::class,
        'hold_type' => HoldType::class,
        'status' => ReservationStatus::class,
        'reserved_starts_at' => 'datetime',
        'reserved_ends_at' => 'datetime',
        'expires_at' => 'datetime',
        'quantity' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * @param  Builder<ServiceInventoryReservation>  $query
     * @return Builder<ServiceInventoryReservation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $this->scopeBlocksInventory($query);
    }

    /**
     * @param  Builder<ServiceInventoryReservation>  $query
     * @return Builder<ServiceInventoryReservation>
     */
    public function scopeBlocksInventory(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('status', ReservationStatus::Confirmed->value)
                ->orWhere(function (Builder $query): void {
                    $query->where('status', ReservationStatus::Held->value)
                        ->where('expires_at', '>', now());
                });
        });
    }

    /**
     * @param  Builder<ServiceInventoryReservation>  $query
     * @return Builder<ServiceInventoryReservation>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Held)
            ->where('expires_at', '<=', now());
    }

    /**
     * @param  Builder<ServiceInventoryReservation>  $query
     * @return Builder<ServiceInventoryReservation>
     */
    public function scopeForService(Builder $query, int $serviceId): Builder
    {
        return $query->where('service_id', $serviceId);
    }

    /**
     * @param  Builder<ServiceInventoryReservation>  $query
     * @return Builder<ServiceInventoryReservation>
     */
    public function scopeOverlapping(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereNotNull('reserved_starts_at')
            ->where('reserved_starts_at', '<', $end)
            ->where('reserved_ends_at', '>', $start);
    }
}

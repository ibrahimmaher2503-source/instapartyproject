<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Enums\FulfillmentIssueReason;
use App\Modules\Booking\Domain\Enums\FulfillmentIssueStatus;
use App\Modules\Booking\Domain\Models\BookingFulfillmentIssue;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingFulfillmentIssue>
 */
class BookingFulfillmentIssueFactory extends Factory
{
    protected $model = BookingFulfillmentIssue::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'booking_vendor_id' => BookingVendor::factory(),
            'booking_item_id' => null,
            'reported_by_user_id' => User::factory(),
            'reason_code' => FulfillmentIssueReason::VenueUnavailable,
            'note' => $this->faker->sentence(),
            'status' => FulfillmentIssueStatus::Open,
            'acknowledged_by_admin_user_id' => null,
            'acknowledged_at' => null,
            'resolved_at' => null,
        ];
    }

    public function acknowledged(?User $admin = null): self
    {
        return $this->state(fn () => [
            'status' => FulfillmentIssueStatus::Acknowledged,
            'acknowledged_by_admin_user_id' => $admin?->id ?? User::factory(),
            'acknowledged_at' => now(),
        ]);
    }

    public function resolved(): self
    {
        return $this->state(fn () => [
            'status' => FulfillmentIssueStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}

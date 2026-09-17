<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment;

use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentIssueDto;
use App\Modules\Booking\Application\Services\FulfillmentGuards;
use App\Modules\Booking\Domain\Enums\FulfillmentIssueStatus;
use App\Modules\Booking\Domain\Events\BookingFulfillmentIssueReported;
use App\Modules\Booking\Domain\Exceptions\FulfillmentIssueAlreadyOpenException;
use App\Modules\Booking\Domain\Models\BookingFulfillmentIssue;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

class ReportFulfillmentIssueAction
{
    public function __construct(
        private readonly FulfillmentGuards $guards,
    ) {}

    public function execute(
        BookingItem $item,
        VendorProfile $vendor,
        User $actor,
        FulfillmentIssueDto $dto,
    ): BookingFulfillmentIssue {
        return DB::transaction(function () use ($item, $vendor, $actor, $dto): BookingFulfillmentIssue {
            $bookingVendor = BookingVendor::query()
                ->whereKey($item->booking_vendor_id)
                ->lockForUpdate()
                ->firstOrFail();

            // FR-EXT-040-019 steps 1-3 (no refund-in-progress check for issue reports: an
            //   active refund is exactly the kind of situation that benefits from an issue note).
            $this->guards->ensureOwningVendor($bookingVendor, $vendor);
            $this->guards->ensurePaymentCaptured($bookingVendor);
            $this->guards->ensureBookingEligible($bookingVendor);

            // FR-EXT-040-018: at most one open issue per item
            $hasOpenIssue = BookingFulfillmentIssue::query()
                ->where('booking_item_id', $item->id)
                ->where('status', FulfillmentIssueStatus::Open)
                ->exists();

            if ($hasOpenIssue) {
                throw new FulfillmentIssueAlreadyOpenException;
            }

            $issue = BookingFulfillmentIssue::create([
                'booking_vendor_id' => $bookingVendor->id,
                'booking_item_id' => $item->id,
                'reported_by_user_id' => $actor->id,
                'reason_code' => $dto->reasonCode,
                'note' => $dto->note,
                'status' => FulfillmentIssueStatus::Open,
            ]);

            foreach ($dto->evidencePhotos as $photo) {
                $issue->addMedia($photo)->toMediaCollection('issue_evidence');
            }

            DB::afterCommit(function () use ($issue, $actor): void {
                event(new BookingFulfillmentIssueReported($issue->refresh(), $actor));
            });

            return $issue->refresh();
        });
    }
}

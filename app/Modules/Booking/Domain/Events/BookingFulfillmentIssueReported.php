<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Models\BookingFulfillmentIssue;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;

final readonly class BookingFulfillmentIssueReported implements AuditableEvent
{
    public function __construct(
        public BookingFulfillmentIssue $issue,
        public User $actor,
    ) {}

    public function auditable(): Model
    {
        return $this->issue;
    }

    public function actor(): ?User
    {
        return $this->actor;
    }

    public function action(): string
    {
        return 'booking.fulfillment_issue.reported';
    }

    public function changes(): array
    {
        return [
            'issue_public_id' => $this->issue->public_id,
            'reason_code' => $this->issue->reason_code->value,
            'booking_vendor_id' => $this->issue->booking_vendor_id,
            'booking_item_id' => $this->issue->booking_item_id,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AlternativeVendorProposalDecided
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $bookingId,
        public readonly int $interventionId,
        public readonly string $decision,
        public readonly int $previousVendorProfileId,
        public readonly ?int $newVendorProfileId,
    ) {}
}

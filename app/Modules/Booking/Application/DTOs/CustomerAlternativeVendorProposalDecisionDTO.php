<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class CustomerAlternativeVendorProposalDecisionDTO
{
    public function __construct(
        public string $bookingPublicId,
        public string $interventionPublicId,
        public int $customerId,
        public string $decision,
        public string $offerHash,
        public ?string $reason = null,
    ) {}
}

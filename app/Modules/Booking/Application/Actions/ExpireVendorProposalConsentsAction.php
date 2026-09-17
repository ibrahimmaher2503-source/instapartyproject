<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\CustomerConsentStatus;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;

final class ExpireVendorProposalConsentsAction
{
    public function execute(): int
    {
        return BookingAdminIntervention::query()
            ->where('customer_consent_status', CustomerConsentStatus::Pending->value)
            ->whereNotNull('consent_expires_at')
            ->where('consent_expires_at', '<=', now())
            ->update(['customer_consent_status' => CustomerConsentStatus::ConsentExpired->value]);
    }
}

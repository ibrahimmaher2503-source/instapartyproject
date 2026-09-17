<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;

final class VendorOperationalAccess
{
    public static function allowed(): bool
    {
        $user = auth()->user();
        $profile = $user?->vendorProfile;

        return $profile !== null
            && $profile->approval_status instanceof ApprovedState
            && $user->hasVerifiedEmail();
    }
}

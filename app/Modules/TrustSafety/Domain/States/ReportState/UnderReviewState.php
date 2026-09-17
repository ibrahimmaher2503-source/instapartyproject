<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\States\ReportState;

final class UnderReviewState extends ReportState
{
    public static string $name = 'under_review';
}

<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\Events;

use App\Modules\TrustSafety\Domain\Models\Report;

final readonly class ReportSubmitted
{
    public function __construct(
        public Report $report,
    ) {}
}

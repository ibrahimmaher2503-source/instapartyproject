<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Application\DTOs;

use App\Modules\TrustSafety\Domain\Enums\ReportReason;

final readonly class SubmitReportDTO
{
    public function __construct(
        public string $reportableType,
        public string $reportablePublicId,
        public ReportReason $reason,
        public ?string $details,
        public int $reporterId,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Infrastructure\Repositories;

use App\Modules\TrustSafety\Application\DTOs\SubmitReportDTO;
use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
use App\Modules\TrustSafety\Domain\Models\Report;
use Illuminate\Support\Str;

class EloquentReportRepository
{
    public function findOpenByReporterAndTarget(int $reporterId, string $type, int $targetId): ?Report
    {
        return Report::where('reporter_id', $reporterId)
            ->where('reportable_type', $type)
            ->where('reportable_id', $targetId)
            ->where('status', ReportStatus::Open->value)
            ->first();
    }

    public function create(SubmitReportDTO $dto, int $resolvedTargetId): Report
    {
        return Report::create([
            'public_id' => (string) Str::ulid(),
            'reporter_id' => $dto->reporterId,
            'reportable_type' => $dto->reportableType,
            'reportable_id' => $resolvedTargetId,
            'reason' => $dto->reason->value,
            'details' => $dto->details,
            'status' => ReportStatus::Open->value,
        ]);
    }
}

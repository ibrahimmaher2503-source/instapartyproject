<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Application\Actions;

use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
use App\Modules\TrustSafety\Domain\Models\Report;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DismissReportAction
{
    public function execute(Report $report): Report
    {
        if ($report->status !== ReportStatus::UnderReview) {
            throw new InvalidArgumentException(
                "Report must be UnderReview to dismiss; current status: {$report->status->value}."
            );
        }

        return DB::transaction(function () use ($report): Report {
            $report->update([
                'status' => ReportStatus::Dismissed,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            return $report->refresh();
        });
    }
}

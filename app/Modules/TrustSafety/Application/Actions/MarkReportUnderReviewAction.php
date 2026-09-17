<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Application\Actions;

use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
use App\Modules\TrustSafety\Domain\Models\Report;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MarkReportUnderReviewAction
{
    public function execute(Report $report): Report
    {
        if ($report->status !== ReportStatus::Open) {
            throw new InvalidArgumentException(
                "Report must be Open to mark under review; current status: {$report->status->value}."
            );
        }

        return DB::transaction(function () use ($report): Report {
            $report->update(['status' => ReportStatus::UnderReview]);

            return $report->refresh();
        });
    }
}

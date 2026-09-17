<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Http\Controllers;

use App\Modules\Shared\Http\ApiResponse;
use App\Modules\TrustSafety\Application\Actions\SubmitReportAction;
use App\Modules\TrustSafety\Application\DTOs\SubmitReportDTO;
use App\Modules\TrustSafety\Domain\Enums\ReportReason;
use App\Modules\TrustSafety\Domain\Exceptions\DuplicateReportException;
use App\Modules\TrustSafety\Http\Requests\SubmitReportRequest;
use App\Modules\TrustSafety\Http\Resources\ReportConfirmationResource;
use Illuminate\Http\JsonResponse;

/**
 * @group Trust & Safety
 */
class ReportController
{
    public function store(SubmitReportRequest $request, SubmitReportAction $action): JsonResponse
    {
        try {
            $dto = new SubmitReportDTO(
                reportableType: $request->validated('reportable_type'),
                reportablePublicId: $request->validated('reportable_id'),
                reason: ReportReason::from($request->validated('reason')),
                details: $request->validated('details'),
                reporterId: $request->user()->id,
            );

            $report = $action->execute($dto);

            return (new ReportConfirmationResource($report))->response()->setStatusCode(201);
        } catch (DuplicateReportException) {
            return ApiResponse::error(
                ['code' => 'REPORT_ALREADY_OPEN', 'message' => 'You have already submitted an open report for this target.'],
                409,
            );
        }
    }
}

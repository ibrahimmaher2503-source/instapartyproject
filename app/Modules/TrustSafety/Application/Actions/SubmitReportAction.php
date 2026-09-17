<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Application\Actions;

use App\Modules\TrustSafety\Application\DTOs\SubmitReportDTO;
use App\Modules\TrustSafety\Domain\Events\ReportSubmitted;
use App\Modules\TrustSafety\Domain\Exceptions\DuplicateReportException;
use App\Modules\TrustSafety\Domain\Models\Report;
use App\Modules\TrustSafety\Infrastructure\Repositories\EloquentReportRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitReportAction
{
    private const REPORTABLE_MODELS = [
        'vendor_profile' => 'App\Modules\Identity\Domain\Models\VendorProfile',
    ];

    public function __construct(
        private readonly EloquentReportRepository $repository,
    ) {}

    public function execute(SubmitReportDTO $dto): Report
    {
        $modelClass = self::REPORTABLE_MODELS[$dto->reportableType]
            ?? throw new InvalidArgumentException("Unknown reportable type: {$dto->reportableType}");

        $target = $modelClass::where('public_id', $dto->reportablePublicId)->firstOrFail();

        $existing = $this->repository->findOpenByReporterAndTarget(
            $dto->reporterId,
            $dto->reportableType,
            $target->id,
        );

        if ($existing !== null) {
            throw new DuplicateReportException;
        }

        $report = DB::transaction(
            fn () => $this->repository->create($dto, $target->id)
        );

        DB::afterCommit(fn () => event(new ReportSubmitted($report)));

        return $report;
    }
}

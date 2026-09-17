<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Infrastructure\Exports\FailedRowsExport;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadFailedRowsAction
{
    public function execute(ExcelImport $import): BinaryFileResponse
    {
        if ($import->error_rows === 0) {
            throw new InvalidArgumentException('No failed rows to export for this import.');
        }

        $errors = $import->errors()->orderBy('row_number')->get();

        // Group by row_number, take the first error's row_data for the full row
        $rows = $errors->groupBy('row_number')
            ->map(fn ($group) => $group->first()->row_data ?? [])
            ->values()
            ->toArray();

        return Excel::download(
            new FailedRowsExport($rows, $import->product_type),
            "failed-rows-{$import->public_id}.xlsx"
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use Illuminate\Support\Facades\DB;

final class FailExcelImportAction
{
    public function execute(ExcelImport $import, int $totalRows = 0): ExcelImport
    {
        DB::transaction(function () use ($import, $totalRows): void {
            $updated = DB::table('excel_imports')
                ->where('id', $import->getKey())
                ->whereNotIn('status', ['completed', 'failed'])
                ->update([
                    'status' => 'failed',
                    'total_rows' => max((int) $import->getAttribute('total_rows'), $totalRows),
                    'imported_rows' => 0,
                    'error_rows' => 1,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                return;
            }

            if (! ExcelImportError::query()
                ->where('excel_import_id', $import->getKey())
                ->where('field', 'import')
                ->exists()) {
                ExcelImportError::create([
                    'excel_import_id' => $import->getKey(),
                    'row_number' => 1,
                    'field' => 'import',
                    'row_data' => null,
                    'message' => [
                        'en' => __('catalog.import_processing_failed', [], 'en'),
                        'ar' => __('catalog.import_processing_failed', [], 'ar'),
                    ],
                ]);
            }
        });

        return $import->refresh();
    }
}

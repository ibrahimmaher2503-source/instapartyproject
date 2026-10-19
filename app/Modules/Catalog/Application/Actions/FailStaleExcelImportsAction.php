<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\ExcelImport;

final class FailStaleExcelImportsAction
{
    public function __construct(private readonly FailExcelImportAction $failImport) {}

    public function execute(int $staleAfterHours = 6): int
    {
        $cutoff = now()->subHours(max(1, $staleAfterHours));
        $failed = 0;

        ExcelImport::query()
            ->whereIn('status', ['pending', 'processing'])
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($imports) use (&$failed): void {
                foreach ($imports as $import) {
                    $this->failImport->execute($import);
                    $failed++;
                }
            });

        return $failed;
    }
}

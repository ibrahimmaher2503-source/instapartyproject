<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class RetryImportAction
{
    public function __construct(
        private readonly ImportRentalServicesFromExcelAction $rentalAction,
        private readonly ImportSaleServicesFromExcelAction $saleAction,
        private readonly ImportDigitalServicesFromExcelAction $digitalAction,
    ) {}

    public function execute(ExcelImport $failedImport, UploadedFile $newFile, int $vendorProfileId): ExcelImport
    {
        if ($failedImport->status !== 'failed') {
            throw new InvalidArgumentException(
                "Cannot retry an import that is not in failed status. Current status: {$failedImport->status}"
            );
        }

        return match ($failedImport->product_type) {
            ProductType::Rental => $this->rentalAction->execute($newFile, $vendorProfileId),
            ProductType::Sale => $this->saleAction->execute($newFile, $vendorProfileId),
            ProductType::Digital => $this->digitalAction->execute($newFile, $vendorProfileId),
        };
    }
}

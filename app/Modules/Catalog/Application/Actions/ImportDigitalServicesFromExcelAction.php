<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\CreateDigitalServiceDTO;
use App\Modules\Catalog\Application\Services\CategoryFieldValidationService;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use App\Modules\Catalog\Infrastructure\Importers\DigitalServicesImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ImportDigitalServicesFromExcelAction
{
    public function __construct(
        private readonly CreateDigitalServiceAction $createDigitalServiceAction,
        private readonly CategoryFieldValidationService $categoryFieldValidator,
        private readonly FailExcelImportAction $failExcelImportAction,
    ) {}

    public function execute(UploadedFile $file, int $vendorProfileId, string $locale = 'en'): ExcelImport
    {
        $storedPath = $file->store('excel-imports', 'local');

        $excelImport = ExcelImport::create([
            'public_id' => Str::ulid()->toBase32(),
            'vendor_profile_id' => $vendorProfileId,
            'product_type' => ProductType::Digital,
            'status' => 'pending',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'total_rows' => 0,
            'imported_rows' => 0,
            'error_rows' => 0,
        ]);

        $totalRows = 0;

        try {
            $excelImport->update(['status' => 'processing']);

            $importer = new DigitalServicesImport;
            Excel::import($importer, $file);
            $rows = $importer->getRows();
            $totalRows = $rows->count();

            if ($rows->isEmpty()) {
                return $this->failImport($excelImport, $totalRows);
            }

            $validationErrors = $this->validateAllRows($rows);

            if ($validationErrors->isNotEmpty()) {
                DB::transaction(function () use ($excelImport, $totalRows, $validationErrors): void {
                    $excelImport->update([
                        'status' => 'failed',
                        'total_rows' => $totalRows,
                        'error_rows' => $validationErrors->count(),
                    ]);

                    foreach ($validationErrors as $error) {
                        ExcelImportError::create([
                            'excel_import_id' => $excelImport->id,
                            'row_number' => $error['row'],
                            'field' => $error['field'],
                            'row_data' => $error['row_data'],
                            'message' => $error['message'],
                        ]);
                    }
                });

                return $excelImport->refresh();
            }

            DB::transaction(function () use ($excelImport, $rows, $totalRows, $vendorProfileId): void {
                foreach ($rows as $row) {
                    $dto = new CreateDigitalServiceDTO(
                        vendorProfileId: $vendorProfileId,
                        categoryId: (int) $row['category_id'],
                        name: ['en' => (string) $row['name_en'], 'ar' => (string) $row['name_ar']],
                        shortDescription: ['en' => (string) $row['short_description_en'], 'ar' => (string) $row['short_description_ar']],
                        basePriceMinor: (int) $row['base_price_minor'],
                        deliveryMethod: (string) $row['delivery_method'],
                        hasExpiry: (bool) $row['has_expiry'],
                        expiryDaysAfterPurchase: isset($row['expiry_days_after_purchase']) && $row['expiry_days_after_purchase'] !== '' ? (int) $row['expiry_days_after_purchase'] : null,
                        isRefundableAfterDelivery: (bool) $row['is_refundable_after_delivery'],
                        redemptionUrlTemplate: isset($row['redemption_url_template']) && $row['redemption_url_template'] !== '' ? (string) $row['redemption_url_template'] : null,
                    );

                    $this->createDigitalServiceAction->execute($dto);
                }

                $excelImport->update([
                    'status' => 'completed',
                    'total_rows' => $totalRows,
                    'imported_rows' => $totalRows,
                    'error_rows' => 0,
                ]);
            });

            return $excelImport->refresh();
        } catch (Throwable $exception) {
            Log::warning('Catalog Excel import failed', [
                'import_public_id' => $excelImport->public_id,
                'exception' => $exception::class,
            ]);

            return $this->failImport($excelImport, $totalRows);
        }
    }

    private function failImport(ExcelImport $import, int $totalRows): ExcelImport
    {
        return $this->failExcelImportAction->execute($import, $totalRows);
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     * @return Collection<int, array{row: int, field: string, row_data: array<string, mixed>, message: array{en: string, ar: string}}>
     */
    private function validateAllRows(Collection $rows): Collection
    {
        $errors = collect();
        $baseRules = [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'short_description_en' => ['required', 'string', 'max:1000'],
            'short_description_ar' => ['required', 'string', 'max:1000'],
            'base_price_minor' => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'integer', 'min:1'],
            'delivery_method' => ['required', 'string', 'in:email,sms,whatsapp,link'],
            'has_expiry' => ['required', 'boolean'],
            'expiry_days_after_purchase' => ['nullable', 'integer', 'min:1'],
            'is_refundable_after_delivery' => ['required', 'boolean'],
            'redemption_url_template' => ['nullable', 'string', 'max:500'],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $rowArray = $row->toArray();

            // Merge category-specific field rules
            $categoryId = (int) ($rowArray['category_id'] ?? 0);
            $rules = $baseRules;
            if ($categoryId > 0) {
                $categoryRules = $this->categoryFieldValidator->rulesFor($categoryId, ProductType::Digital);
                $rules = array_merge($rules, $categoryRules);
            }

            $enErrors = Validator::make($rowArray, $rules)->errors()->toArray();

            // Conditional: expiry_days_after_purchase required when has_expiry is truthy
            if ((bool) ($rowArray['has_expiry'] ?? false) && empty($rowArray['expiry_days_after_purchase'])) {
                $enErrors['expiry_days_after_purchase'][] = 'The expiry days after purchase field is required when has expiry is true.';
            }

            if (! empty($enErrors)) {
                app()->setLocale('ar');
                $arErrors = Validator::make($rowArray, $rules)->errors()->toArray();

                // Add Arabic conditional error for expiry_days_after_purchase if needed
                if (isset($enErrors['expiry_days_after_purchase']) && ! isset($arErrors['expiry_days_after_purchase'])) {
                    $arErrors['expiry_days_after_purchase'][] = 'حقل أيام انتهاء الصلاحية مطلوب عندما يكون للمنتج تاريخ انتهاء.';
                }

                app()->setLocale('en');

                foreach ($enErrors as $field => $enMessages) {
                    $errors->push([
                        'row' => $rowNumber,
                        'field' => $field,
                        'row_data' => $rowArray,
                        'message' => [
                            'en' => implode(' ', $enMessages),
                            'ar' => implode(' ', $arErrors[$field] ?? $enMessages),
                        ],
                    ]);
                }
            }
        }

        return $errors;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Exports;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FailedRowsExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly array $rows,
        private readonly ProductType $productType,
    ) {}

    public function headings(): array
    {
        return match ($this->productType) {
            ProductType::Rental => ['name_en', 'name_ar', 'short_description_en', 'short_description_ar', 'category_id', 'base_price_minor', 'requires_electricity', 'requires_outdoor_space', 'default_rental_duration_hours', 'setup_time_minutes', 'teardown_time_minutes', 'security_deposit_minor', 'minimum_space_sqm'],
            ProductType::Sale => ['name_en', 'name_ar', 'short_description_en', 'short_description_ar', 'category_id', 'base_price_minor', 'is_perishable', 'is_made_to_order', 'lead_time_hours', 'stock_quantity'],
            ProductType::Digital => ['name_en', 'name_ar', 'short_description_en', 'short_description_ar', 'category_id', 'base_price_minor', 'delivery_method', 'is_refundable_after_delivery'],
        };
    }

    public function array(): array
    {
        if (empty($this->rows)) {
            return [array_fill(0, count($this->headings()), '')];
        }

        return array_map(function (array $row): array {
            return array_values(array_map(
                fn (string $col) => $row[$col] ?? '',
                $this->headings()
            ));
        }, $this->rows);
    }
}

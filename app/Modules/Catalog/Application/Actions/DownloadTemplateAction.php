<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadTemplateAction
{
    public function execute(ProductType $type): BinaryFileResponse
    {
        [$export, $filename] = match ($type) {
            ProductType::Rental => [new RentalTemplateExport, 'rental-template.xlsx'],
            ProductType::Sale => [new SaleTemplateExport, 'sale-template.xlsx'],
            ProductType::Digital => [new DigitalTemplateExport, 'digital-template.xlsx'],
        };

        return Excel::download($export, $filename);
    }
}

class RentalTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'name_en', 'name_ar', 'short_description_en', 'short_description_ar',
            'category_id', 'base_price_minor', 'requires_electricity',
            'requires_outdoor_space', 'default_rental_duration_hours',
            'setup_time_minutes', 'teardown_time_minutes',
            'security_deposit_minor', 'minimum_space_sqm',
        ];
    }

    public function array(): array
    {
        return [['My Rental Service', 'خدمة إيجاري', 'Short description', 'وصف قصير', 1, 10000, 1, 0, 4, 30, 30, 5000, 20]];
    }
}

class SaleTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'name_en', 'name_ar', 'short_description_en', 'short_description_ar',
            'category_id', 'base_price_minor', 'is_perishable',
            'is_made_to_order', 'lead_time_hours', 'stock_quantity',
        ];
    }

    public function array(): array
    {
        return [['My Sale Service', 'خدمة البيع', 'Short description', 'وصف قصير', 1, 8000, 1, 0, null, 50]];
    }
}

class DigitalTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'name_en', 'name_ar', 'short_description_en', 'short_description_ar',
            'category_id', 'base_price_minor', 'delivery_method',
            'is_refundable_after_delivery',
        ];
    }

    public function array(): array
    {
        return [['My Digital Service', 'خدمة رقمية', 'Short description', 'وصف قصير', 1, 5000, 'email', 1]];
    }
}

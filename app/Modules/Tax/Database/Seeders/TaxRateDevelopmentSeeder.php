<?php

declare(strict_types=1);

namespace App\Modules\Tax\Database\Seeders;

use App\Modules\Tax\Domain\Enums\TaxAppliesTo;
use App\Modules\Tax\Domain\Models\TaxRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TaxRateDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'name' => ['en' => 'Standard VAT (Egypt)', 'ar' => 'ضريبة القيمة المضافة (مصر)'],
                'description' => ['en' => 'Default 14% VAT applied to all taxable services.', 'ar' => 'ضريبة قيمة مضافة افتراضية بنسبة 14٪ تنطبق على جميع الخدمات الخاضعة للضريبة.'],
                'rate_bps' => 1400,
                'applies_to' => TaxAppliesTo::All,
                'product_types' => ['rental', 'sale', 'digital'],
                'is_tax_inclusive' => false,
                'effective_from' => now()->subYear()->startOfYear()->toDateString(),
            ],
            [
                'name' => ['en' => 'Reduced VAT — Digital Goods', 'ar' => 'ضريبة مخفضة — منتجات رقمية'],
                'description' => ['en' => '5% reduced VAT for digital deliverables.', 'ar' => 'ضريبة مخفضة 5٪ للمنتجات الرقمية.'],
                'rate_bps' => 500,
                'applies_to' => TaxAppliesTo::Customer,
                'product_types' => ['digital'],
                'is_tax_inclusive' => true,
                'effective_from' => now()->startOfMonth()->toDateString(),
            ],
        ];

        foreach ($rows as $row) {
            TaxRate::query()->updateOrCreate(
                ['rate_bps' => $row['rate_bps'], 'applies_to' => $row['applies_to']],
                array_merge($row, [
                    'public_id' => (string) Str::ulid(),
                    'is_active' => true,
                    'effective_to' => null,
                ]),
            );
        }
    }
}

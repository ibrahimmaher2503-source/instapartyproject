<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\DigitalTemplateExport;
use App\Modules\Catalog\Application\Actions\DownloadTemplateAction;
use App\Modules\Catalog\Application\Actions\RentalTemplateExport;
use App\Modules\Catalog\Application\Actions\RetryImportAction;
use App\Modules\Catalog\Application\Actions\SaleTemplateExport;
use App\Modules\Catalog\Database\Factories\ExcelImportFactory;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Application\DTOs\PolicyDecisionDto;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionPolicyContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

it('imports valid workbooks for each approved product type and exposes their history', function (): void {
    Storage::fake('local');
    config(['catalog.digital_services_enabled' => true]);
    $vendor = VendorProfile::factory()->approved()->create();
    $policy = Mockery::mock(SubscriptionPolicyContract::class);
    $policy->shouldReceive('canCreateService')->times(3)->andReturn(PolicyDecisionDto::allow());
    app()->instance(SubscriptionPolicyContract::class, $policy);

    $occasion = Occasion::factory()->create();
    foreach (ProductType::cases() as $type) {
        VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $vendor->id]);
        $category = Category::factory()->create(['allowed_product_types' => [$type->value]]);
        $category->occasions()->attach($occasion);

        if ($type === ProductType::Rental) {
            CategoryFieldSchema::factory()->create([
                'category_id' => $category->id,
                'product_type' => $type,
                'field_key' => 'capacity',
                'field_type' => 'number',
                'is_required' => true,
            ]);
        }

        Sanctum::actingAs($vendor->user->fresh());
        $templateResponse = $this->get('/api/v1/vendor/catalog/import/template/'.$type->value);
        $templateResponse->assertDownload($type->value.'-template.xlsx');

        $response = $this->postJson("/api/v1/vendor/services/{$type->value}/import", [
            'store_id' => $vendor->public_id,
            'file' => remainingCatalogImportWorkbook($type, $category->id),
        ]);
        $response->assertOk()->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.imported_rows', 1)
            ->assertJsonPath('data.total_rows', 1);

        $service = Service::query()->where('vendor_profile_id', $vendor->id)->where('product_type', $type)->sole();
        $import = ExcelImport::query()->where('vendor_profile_id', $vendor->id)->where('product_type', $type)->sole();

        expect($service->category_id)->toBe($category->id)
            ->and($service->base_price_minor)->toBe(12500)
            ->and($service->base_price_currency)->toBe('EGP')
            ->and($service->status->getValue())->toBe('draft')
            ->and($service->category->occasions->modelKeys())->toContain($occasion->id)
            ->and($import->status)->toBe('completed')
            ->and($import->total_rows)->toBe(1)
            ->and($import->imported_rows)->toBe(1)
            ->and($import->error_rows)->toBe(0);

        if ($type === ProductType::Rental) {
            expect($service->category_field_values)->toBe(['capacity' => 50]);
        }

        $this->getJson('/api/v1/vendor/catalog/import/'.$import->public_id)
            ->assertOk()
            ->assertJsonPath('data.product_type', $type->value)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.imported_rows', 1);
    }

});

it('records invalid workbook rows in history and serves the failed-row download', function (): void {
    Storage::fake('local');
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Rental)->create(['vendor_profile_id' => $vendor->id]);
    $category = Category::factory()->create(['allowed_product_types' => [ProductType::Rental->value]]);
    Sanctum::actingAs($vendor->user);

    $response = $this->postJson('/api/v1/vendor/services/rental/import', [
        'store_id' => $vendor->public_id,
        'file' => remainingCatalogImportWorkbook(ProductType::Rental, $category->id, ['name_en' => '']),
    ])->assertUnprocessable()->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.imported_rows', 0)
        ->assertJsonPath('data.total_rows', 1);

    $import = ExcelImport::query()->sole();
    $error = $import->errors()->where('field', 'name_en')->firstOrFail();
    expect($error->row_data)->toBeArray()
        ->and($error->message['en'])->not->toBeEmpty()
        ->and($error->message['ar'])->not->toBeEmpty()
        ->and(Service::query()->count())->toBe(0);

    $this->getJson('/api/v1/vendor/catalog/import/'.$import->public_id)
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error_rows', 1);
    $this->get('/api/v1/vendor/catalog/import/'.$import->public_id.'/failed-rows')
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=failed-rows-'.$import->public_id.'.xlsx');

    $this->postJson('/api/v1/vendor/services/rental/import', [
        'store_id' => $vendor->public_id,
        'file' => UploadedFile::fake()->createWithContent('malformed.xlsx', 'not an Excel workbook'),
    ])->assertUnprocessable()->assertJsonPath('data.status', 'failed');
    $malformed = ExcelImport::query()->where('original_filename', 'malformed.xlsx')->firstOrFail();
    expect($malformed->errors()->value('field'))->toBe('import')
        ->and(Service::query()->count())->toBe(0);
});

it('rejects another stores import and does not reveal its history', function (): void {
    $owner = VendorProfile::factory()->approved()->create();
    $other = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Rental)->create(['vendor_profile_id' => $owner->id]);
    VendorApprovedProductType::factory()->forType(ProductType::Rental)->create(['vendor_profile_id' => $other->id]);
    $import = ExcelImportFactory::new()->create(['vendor_profile_id' => $owner->id]);
    Sanctum::actingAs($other->user);

    $this->postJson('/api/v1/vendor/services/rental/import', [
        'store_id' => $owner->public_id,
        'file' => remainingCatalogImportWorkbook(ProductType::Rental, Category::factory()->create()->id),
    ])->assertForbidden();
    $this->getJson('/api/v1/vendor/catalog/import/'.$import->public_id)->assertNotFound();
    $this->get('/api/v1/vendor/catalog/import/'.$import->public_id.'/failed-rows')->assertForbidden();
    expect(ExcelImport::query()->count())->toBe(1);
});

it('keeps the digital template inside the enabled approved-vendor scope', function (): void {
    config(['catalog.digital_services_enabled' => true]);
    $vendor = VendorProfile::factory()->approved()->create();
    Sanctum::actingAs($vendor->user);

    $this->get('/api/v1/vendor/catalog/import/template/digital')->assertNotFound();

    VendorApprovedProductType::factory()->forType(ProductType::Digital)->create([
        'vendor_profile_id' => $vendor->id,
    ]);
    Sanctum::actingAs($vendor->user->fresh());
    $this->get('/api/v1/vendor/catalog/import/template/digital')->assertDownload('digital-template.xlsx');

    config(['catalog.digital_services_enabled' => false]);
    $this->get('/api/v1/vendor/catalog/import/template/digital')->assertNotFound();

    $this->actingAs(User::factory()->superAdmin()->create());
    $this->get('/admin/catalog/import/template/digital')->assertDownload('digital-template.xlsx');
});

it('replays a keyed import once while a distinct key permits a fresh upload', function (): void {
    Storage::fake('local');
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Rental)->create(['vendor_profile_id' => $vendor->id]);
    $category = Category::factory()->create(['allowed_product_types' => [ProductType::Rental->value]]);
    $policy = Mockery::mock(SubscriptionPolicyContract::class);
    $policy->shouldReceive('canCreateService')->times(3)->andReturn(PolicyDecisionDto::allow());
    app()->instance(SubscriptionPolicyContract::class, $policy);
    Sanctum::actingAs($vendor->user);

    $url = '/api/v1/vendor/services/rental/import';
    $payload = [
        'store_id' => $vendor->public_id,
        'file' => remainingCatalogImportWorkbook(ProductType::Rental, $category->id),
    ];
    $first = $this->postJson($url, $payload, ['Idempotency-Key' => 'catalog-import-replay-1'])
        ->assertOk()->assertJsonPath('data.status', 'completed');
    $replay = $this->postJson($url, $payload, ['Idempotency-Key' => 'catalog-import-replay-1'])
        ->assertOk();

    expect($replay->json())->toBe($first->json())
        ->and(Service::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(1)
        ->and(ExcelImport::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(1);

    $this->postJson($url, [
        'store_id' => $vendor->public_id,
        'file' => remainingCatalogImportWorkbook(ProductType::Rental, $category->id, ['name_en' => 'Different workbook']),
    ], ['Idempotency-Key' => 'catalog-import-replay-1'])->assertConflict();

    $this->postJson($url, [
        'store_id' => $vendor->public_id,
        'file' => remainingCatalogImportWorkbook(ProductType::Rental, $category->id),
    ], ['Idempotency-Key' => 'catalog-import-replay-2'])
        ->assertOk()->assertJsonPath('data.status', 'completed');

    expect(Service::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(2)
        ->and(ExcelImport::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(2);

    $this->postJson($url, [
        'store_id' => $vendor->public_id,
        'file' => remainingCatalogImportWorkbook(ProductType::Rental, $category->id, ['name_en' => 'Legacy unkeyed upload']),
    ])->assertOk()->assertJsonPath('data.status', 'completed');

    expect(Service::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(3)
        ->and(ExcelImport::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(3);
});

it('validates malformed file arrays before optional idempotency hashing', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Rental)->create(['vendor_profile_id' => $vendor->id]);
    Sanctum::actingAs($vendor->user);

    $this->postJson('/api/v1/vendor/services/rental/import', [
        'store_id' => $vendor->public_id,
        'file' => [UploadedFile::fake()->createWithContent('malformed.xlsx', 'not an Excel workbook')],
    ], ['Idempotency-Key' => 'catalog-import-malformed-file'])
        ->assertUnprocessable();
});

it('rolls back a partial import and retries the corrected workbook as a new history record', function (): void {
    Storage::fake('local');
    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Rental)->create(['vendor_profile_id' => $vendor->id]);
    $category = Category::factory()->create(['allowed_product_types' => [ProductType::Rental->value]]);

    $failingPolicy = Mockery::mock(SubscriptionPolicyContract::class);
    $calls = 0;
    $failingPolicy->shouldReceive('canCreateService')->twice()->andReturnUsing(function () use (&$calls): PolicyDecisionDto {
        if (++$calls === 2) {
            throw new RuntimeException('Simulated interrupted import after first service write.');
        }

        return PolicyDecisionDto::allow();
    });
    app()->instance(SubscriptionPolicyContract::class, $failingPolicy);
    Sanctum::actingAs($vendor->user);

    $failedResponse = $this->postJson('/api/v1/vendor/services/rental/import', [
        'store_id' => $vendor->public_id,
        'file' => remainingCatalogImportWorkbook(ProductType::Rental, $category->id, [], [
            ['name_en' => 'Second recovery row', 'name_ar' => 'السطر الثاني للاستعادة'],
        ]),
    ])->assertUnprocessable()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.imported_rows', 0)
        ->assertJsonPath('data.total_rows', 2);

    $failedImport = ExcelImport::query()->where('vendor_profile_id', $vendor->id)->sole();
    expect($failedImport->status)->toBe('failed')
        ->and(Service::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(0);

    $retryPolicy = Mockery::mock(SubscriptionPolicyContract::class);
    $retryPolicy->shouldReceive('canCreateService')->twice()->andReturn(PolicyDecisionDto::allow());
    app()->instance(SubscriptionPolicyContract::class, $retryPolicy);

    $retried = app(RetryImportAction::class)->execute(
        $failedImport,
        remainingCatalogImportWorkbook(ProductType::Rental, $category->id, [], [
            ['name_en' => 'Second recovery row', 'name_ar' => 'السطر الثاني للاستعادة'],
        ]),
        $vendor->id,
    );

    expect($retried->id)->not->toBe($failedImport->id)
        ->and($retried->status)->toBe('completed')
        ->and($retried->total_rows)->toBe(2)
        ->and($retried->imported_rows)->toBe(2)
        ->and($failedImport->fresh()->status)->toBe('failed')
        ->and(ExcelImport::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(2)
        ->and(Service::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(2);
});

it('rejects exact duplicate rows in rental sale and digital workbooks with bilingual row errors', function (): void {
    Storage::fake('local');
    config(['catalog.digital_services_enabled' => true]);
    $vendor = VendorProfile::factory()->approved()->create();
    Sanctum::actingAs($vendor->user);

    foreach (ProductType::cases() as $type) {
        VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $vendor->id]);
        $category = Category::factory()->create(['allowed_product_types' => [$type->value]]);
        Sanctum::actingAs($vendor->user->fresh());
        $response = $this->postJson("/api/v1/vendor/services/{$type->value}/import", [
            'store_id' => $vendor->public_id,
            'file' => remainingCatalogImportWorkbook($type, $category->id, [], [[]]),
        ])->assertUnprocessable()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.imported_rows', 0)
            ->assertJsonPath('data.total_rows', 2);

        $import = ExcelImport::query()->where('vendor_profile_id', $vendor->id)->where('product_type', $type)->sole();
        $error = $import->errors()->sole();
        expect($error->field)->toBe('duplicate_row')
            ->and($error->row_number)->toBe(3)
            ->and($error->message['en'])->not->toBeEmpty()
            ->and($error->message['ar'])->not->toBeEmpty();
    }

    expect(Service::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(0)
        ->and(ExcelImport::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(3);
});

function remainingCatalogImportWorkbook(ProductType $type, int $categoryId, array $overrides = [], array $additionalRows = []): UploadedFile
{
    app(DownloadTemplateAction::class);
    $template = match ($type) {
        ProductType::Rental => new RentalTemplateExport,
        ProductType::Sale => new SaleTemplateExport,
        ProductType::Digital => new DigitalTemplateExport,
    };
    $headings = $template->headings();
    $row = array_combine($headings, $template->array()[0]);
    $row['name_en'] = 'Import lifecycle service';
    $row['name_ar'] = 'خدمة اختبار الاستيراد';
    $row['short_description_en'] = 'Import lifecycle QA';
    $row['short_description_ar'] = 'اختبار دورة الاستيراد';
    $row['category_id'] = $categoryId;
    $row['base_price_minor'] = 12500;

    if ($type === ProductType::Rental) {
        $row['capacity'] = 50;
    }
    if ($type === ProductType::Sale) {
        $row['is_perishable'] = 0;
        $row['is_made_to_order'] = 1;
        $row['lead_time_hours'] = 24;
    }
    if ($type === ProductType::Digital) {
        $row['delivery_method'] = 'email';
        $row['has_expiry'] = 1;
        $row['expiry_days_after_purchase'] = 30;
    }

    $row = array_replace($row, $overrides);
    $rows = [$row];
    foreach ($additionalRows as $additionalRow) {
        $rows[] = array_replace($row, $additionalRow);
    }
    $workbook = Excel::raw(new RemainingCatalogImportWorkbook($rows), ExcelFormat::XLSX);

    return UploadedFile::fake()->createWithContent($type->value.'-services.xlsx', $workbook);
}

final class RemainingCatalogImportWorkbook implements FromArray, WithHeadings, WithStrictNullComparison
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return array_keys($this->rows[0]);
    }

    public function array(): array
    {
        return array_map('array_values', $this->rows);
    }
}

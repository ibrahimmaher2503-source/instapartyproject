<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\CreateRentalServiceAction;
use App\Modules\Catalog\Application\Actions\FailExcelImportAction;
use App\Modules\Catalog\Application\Actions\ImportDigitalServicesFromExcelAction;
use App\Modules\Catalog\Application\Actions\ImportRentalServicesFromExcelAction;
use App\Modules\Catalog\Application\Actions\ImportSaleServicesFromExcelAction;
use App\Modules\Catalog\Application\Services\CategoryFieldValidationService;
use App\Modules\Catalog\Database\Factories\ExcelImportFactory;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('records sanitized failure state for parser or worker errors across product types', function (): void {
    Storage::fake('local');
    Excel::shouldReceive('import')->times(3)->andThrow(new RuntimeException('private parser path /tmp/secrets.xlsx'));
    $vendor = VendorProfile::factory()->create();

    $actions = [
        app(ImportRentalServicesFromExcelAction::class),
        app(ImportSaleServicesFromExcelAction::class),
        app(ImportDigitalServicesFromExcelAction::class),
    ];

    $imports = [];
    foreach ($actions as $index => $action) {
        $imports[] = $action->execute(
            UploadedFile::fake()->create('broken-'.$index.'.xlsx', 1),
            $vendor->id,
        );
    }

    foreach ($imports as $import) {
        $error = ExcelImportError::query()->where('excel_import_id', $import->id)->firstOrFail();

        expect($import->status)->toBe('failed')
            ->and($import->total_rows)->toBe(0)
            ->and($import->imported_rows)->toBe(0)
            ->and($import->error_rows)->toBe(1)
            ->and($error->field)->toBe('import')
            ->and($error->row_data)->toBeNull()
            ->and($error->message['en'])->not->toContain('private parser path')
            ->and($error->message['ar'])->not->toContain('private parser path');
    }
});

it('marks empty files as failed instead of completing zero rows', function (): void {
    Storage::fake('local');
    $vendor = VendorProfile::factory()->create();
    $file = UploadedFile::fake()->createWithContent('empty.csv', "name_en,name_ar,category_id\n");

    $import = app(ImportRentalServicesFromExcelAction::class)->execute($file, $vendor->id);

    expect($import->status)->toBe('failed')
        ->and($import->total_rows)->toBe(0)
        ->and($import->imported_rows)->toBe(0)
        ->and($import->error_rows)->toBe(1)
        ->and($import->errors()->value('field'))->toBe('import');
});

it('stores bilingual row errors and leaves invalid imports without services', function (): void {
    Storage::fake('local');
    $vendor = VendorProfile::factory()->create();
    $file = UploadedFile::fake()->createWithContent('invalid.csv', implode(',', [
        'name_en', 'name_ar', 'short_description_en', 'short_description_ar', 'base_price_minor',
        'requires_electricity', 'requires_outdoor_space', 'default_rental_duration_hours', 'category_id',
    ])."\n".implode(',', [
        '', 'كراسي حفلات', 'Chairs', 'كراسي', '2500', '0', '0', '24', '1',
    ])."\n");

    $import = app(ImportRentalServicesFromExcelAction::class)->execute($file, $vendor->id);
    $error = $import->errors()->firstOrFail();

    expect($import->status)->toBe('failed')
        ->and($import->total_rows)->toBe(1)
        ->and($import->imported_rows)->toBe(0)
        ->and($import->error_rows)->toBeGreaterThan(0)
        ->and($error->field)->toBe('name_en')
        ->and($error->row_data)->toBeArray()
        ->and($error->message['en'])->not->toBeEmpty()
        ->and($error->message['ar'])->not->toBeEmpty()
        ->and(Service::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(0);
});

it('records a sanitized failure when service creation fails after parsing', function (): void {
    Storage::fake('local');
    $vendor = VendorProfile::factory()->create();
    $validator = Mockery::mock(CategoryFieldValidationService::class);
    $validator->shouldReceive('rulesFor')->once()->andReturn([]);
    $creator = Mockery::mock(CreateRentalServiceAction::class);
    $creator->shouldReceive('execute')->once()->andThrow(new RuntimeException('database secret /srv/private.xlsx'));

    $action = new ImportRentalServicesFromExcelAction($creator, $validator, new FailExcelImportAction);
    $file = UploadedFile::fake()->createWithContent('worker-failure.csv', implode(',', [
        'name_en', 'name_ar', 'short_description_en', 'short_description_ar', 'base_price_minor',
        'requires_electricity', 'requires_outdoor_space', 'default_rental_duration_hours', 'category_id',
    ])."\n".implode(',', [
        'Party Chairs', 'كراسي حفلات', 'Chairs', 'كراسي', '2500', '0', '0', '24', '1',
    ])."\n");

    $import = $action->execute($file, $vendor->id);
    $error = $import->errors()->firstOrFail();

    expect($import->status)->toBe('failed')
        ->and($import->total_rows)->toBe(1)
        ->and($import->imported_rows)->toBe(0)
        ->and($import->error_rows)->toBe(1)
        ->and($error->field)->toBe('import')
        ->and($error->message['en'])->not->toContain('database secret')
        ->and(Service::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(0);
});

it('imports a valid rental row and commits completed counters', function (): void {
    Storage::fake('local');
    $vendor = VendorProfile::factory()->create();
    $validator = Mockery::mock(CategoryFieldValidationService::class);
    $validator->shouldReceive('rulesFor')->once()->andReturn([]);
    $creator = Mockery::mock(CreateRentalServiceAction::class);
    $creator->shouldReceive('execute')->once()->andReturn(new Service);

    $action = new ImportRentalServicesFromExcelAction($creator, $validator, new FailExcelImportAction);
    $file = UploadedFile::fake()->createWithContent('valid.csv', implode(',', [
        'name_en', 'name_ar', 'short_description_en', 'short_description_ar', 'base_price_minor',
        'requires_electricity', 'requires_outdoor_space', 'default_rental_duration_hours', 'category_id',
    ])."\n".implode(',', [
        'Party Chairs', 'كراسي حفلات', 'Chairs', 'كراسي', '2500', '0', '0', '24', '1',
    ])."\n");

    $import = $action->execute($file, $vendor->id);

    expect($import->status)->toBe('completed')
        ->and($import->total_rows)->toBe(1)
        ->and($import->imported_rows)->toBe(1)
        ->and($import->error_rows)->toBe(0);
});

it('renders every import product type and malformed error rows in admin detail', function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_excel::import', 'view_excel::import'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($admin);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));

    foreach (['rental', 'sale', 'digital'] as $type) {
        $import = ExcelImportFactory::new()->create([
            'product_type' => $type,
            'status' => 'failed',
            'total_rows' => 1,
            'imported_rows' => 0,
            'error_rows' => 1,
        ]);
        ExcelImportError::factory()->create([
            'excel_import_id' => $import->id,
            'row_number' => 1,
            'field' => null,
            'row_data' => null,
            'message' => ['en' => 'The file could not be processed.', 'ar' => 'تعذر معالجة الملف.'],
        ]);

        $this->get('/admin/excel-imports/'.$import->public_id)->assertOk();
    }
});

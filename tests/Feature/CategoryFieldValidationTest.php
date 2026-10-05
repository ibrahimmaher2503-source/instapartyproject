<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\ImportRentalServicesFromExcelAction;
use App\Modules\Catalog\Application\Services\CategoryFieldPresenter;
use App\Modules\Catalog\Application\Services\CategoryFieldValidationService;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Http\Resources\CustomerServiceDetailResource;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function categorySchemaFixture(ProductType $type, string $key = 'capacity', array $overrides = []): array
{
    $root = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => [$type->value]]);
    $child = Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => [$type->value]]);
    $schema = CategoryFieldSchema::factory()->create(array_merge([
        'category_id' => $child->id, 'product_type' => $type, 'field_key' => $key,
        'field_label' => ['en' => 'Capacity', 'ar' => 'السعة'], 'field_type' => 'number',
        'is_required' => true, 'validation_rules' => ['integer', 'min:1', 'max:12'],
        'sort_order' => 10,
    ], $overrides));

    return [$root, $child, $schema, app(CategoryFieldValidationService::class)];
}

it('inherits shared parent fields with nearest child overrides and type isolation', function (ProductType $type): void {
    [$root, $child, $schema, $validator] = categorySchemaFixture($type);
    CategoryFieldSchema::factory()->create(['category_id' => $root->id, 'product_type' => $type,
        'field_key' => 'capacity', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 99]);
    CategoryFieldSchema::factory()->create(['category_id' => $root->id, 'product_type' => $type,
        'field_key' => 'theme', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 0]);
    $other = $type === ProductType::Sale ? ProductType::Rental : ProductType::Sale;
    CategoryFieldSchema::factory()->create(['category_id' => $child->id, 'product_type' => $other,
        'field_key' => 'foreign_type', 'field_type' => 'text', 'is_required' => true]);
    expect($validator->schemasFor($child->id, $type)->pluck('field_key')->all())->toBe(['theme', 'capacity'])
        ->and($validator->schemasFor($child->id, $type)->last()->id)->toBe($schema->id)
        ->and($validator->validateValues($child->id, $type, ['theme' => 'Birthday', 'capacity' => 8]))
        ->toBe(['theme' => 'Birthday', 'capacity' => 8]);
    expect(fn () => $validator->validateValues($child->id, $type, ['capacity' => 8]))->toThrow(ValidationException::class);
})->with(ProductType::cases());

it('validates all six native field types even when optional and without custom rules', function (string $fieldType, mixed $valid, mixed $invalid, ProductType $type): void {
    [, $child, , $validator] = categorySchemaFixture($type, 'feature', [
        'field_type' => $fieldType, 'is_required' => false, 'validation_rules' => null,
        'options' => [['value' => 'clown', 'label' => ['en' => 'Clown', 'ar' => 'مهرج']],
            ['value' => 'magician', 'label' => ['en' => 'Magician', 'ar' => 'ساحر']]],
    ]);
    expect($validator->validateValues($child->id, $type, []))->toBe([])
        ->and($validator->validateValues($child->id, $type, ['feature' => null]))->toBe(['feature' => null])
        ->and($validator->validateValues($child->id, $type, ['feature' => $valid]))->toBe(['feature' => $valid]);
    expect(fn () => $validator->validateValues($child->id, $type, ['feature' => $invalid]))
        ->toThrow(ValidationException::class);
})->with([
    'text' => ['text', 'Birthday', ['nested' => 'value']],
    'number' => ['number', 0, 'large'],
    'boolean' => ['boolean', false, 'yes'],
    'select' => ['select', 'clown', 'not-an-option'],
    'multiselect' => ['multiselect', ['clown', 'magician'], ['clown', 'not-an-option']],
    'date' => ['date', '2026-10-05', '2026-02-30'],
])->with(ProductType::cases());

it('accepts localized seeded and legacy option formats without accepting unknown choices', function (string $locale): void {
    app()->setLocale($locale);
    [, $child, $schema, $validator] = categorySchemaFixture(ProductType::Rental, 'act', ['field_type' => 'select',
        'validation_rules' => null, 'options' => [['value' => 'clown', 'label' => ['en' => 'Clown', 'ar' => 'مهرج']]]]);
    expect($validator->optionsFor($schema))->toBe(['clown' => $locale === 'ar' ? 'مهرج' : 'Clown']);
    foreach ([['clown', 'magician'], ['clown' => 'Clown', 'magician' => 'Magician'],
        ['clown' => ['en' => 'Clown', 'ar' => 'مهرج']]] as $options) {
        $schema->update(['options' => $options]);
        $validator = app(CategoryFieldValidationService::class);
        expect($validator->validateValues($child->id, ProductType::Rental, ['act' => 'clown']))->toBe(['act' => 'clown']);
        expect(fn () => $validator->validateValues($child->id, ProductType::Rental, ['act' => 'unknown']))->toThrow(ValidationException::class);
    }
})->with(['en', 'ar']);

it('supports the admin KeyValue rule format and refreshes definitions in a new validator', function (): void {
    [, $child, $schema, $validator] = categorySchemaFixture(ProductType::Rental, 'capacity', [
        'validation_rules' => ['integer' => true, 'min' => 1, 'max' => 12],
    ]);
    expect($validator->validateValues($child->id, ProductType::Rental, ['capacity' => 8]))->toBe(['capacity' => 8]);
    foreach ([0, 13, '1.5'] as $value) {
        expect(fn () => $validator->validateValues($child->id, ProductType::Rental, ['capacity' => $value]))->toThrow(ValidationException::class);
    }
    $schema->update(['validation_rules' => ['integer' => true, 'min' => 1, 'max' => 5]]);
    $validator = app(CategoryFieldValidationService::class);
    expect(fn () => $validator->validateValues($child->id, ProductType::Rental, ['capacity' => 8]))->toThrow(ValidationException::class);
});

it('does not repeat schema queries for every row of a bulk import', function (ProductType $type): void {
    [, $child, , $validator] = categorySchemaFixture($type);
    DB::enableQueryLog();
    $before = count(DB::getQueryLog());
    for ($row = 0; $row < 100; $row++) {
        $validator->rulesFor($child->id, $type);
    }
    expect(count(DB::getQueryLog()) - $before)->toBe(3);
    DB::disableQueryLog();
})->with(ProductType::cases());

it('does not let nullable sometimes or exclusion rules bypass a required schema', function (): void {
    [, $child, , $validator] = categorySchemaFixture(ProductType::Rental, 'capacity', [
        'validation_rules' => ['sometimes', 'nullable', 'exclude'],
    ]);
    expect(fn () => $validator->validateValues($child->id, ProductType::Rental, []))->toThrow(ValidationException::class)
        ->and(fn () => $validator->validateValues($child->id, ProductType::Rental, ['capacity' => null]))->toThrow(ValidationException::class)
        ->and($validator->validateValues($child->id, ProductType::Rental, ['capacity' => 8]))->toBe(['capacity' => 8]);
});

it('rejects fields belonging to another category or type using localized errors', function (string $locale, ProductType $type): void {
    app()->setLocale($locale);
    [, $child, , $validator] = categorySchemaFixture($type);
    try {
        $validator->validateValues($child->id, $type, ['capacity' => 8, 'vendor_profile_id' => 99]);
        $this->fail('An unknown field must not be accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['vendor_profile_id'])->toBe([__('catalog.category_field_unknown')]);
    }
})->with(['en', 'ar'])->with(ProductType::cases());

it('stops cyclic ancestry and returns no schemas for a missing or deleted category', function (): void {
    [$root, $child, , $validator] = categorySchemaFixture(ProductType::Rental);
    $root->update(['parent_id' => $child->id]);
    expect($validator->schemasFor($child->id, ProductType::Rental)->count())->toBe(1)
        ->and($validator->schemasFor(999999, ProductType::Rental))->toBeEmpty();
    $child->delete();
    expect($validator->schemasFor($child->id, ProductType::Rental))->toBeEmpty();
});

it('enforces shared numeric and select schemas in the real Excel import before service creation', function (string $field, string $value): void {
    Storage::fake('local');
    [$root, $child] = categorySchemaFixture(ProductType::Rental);
    CategoryFieldSchema::query()->where('category_id', $child->id)->delete();
    CategoryFieldSchema::factory()->create(['category_id' => $root->id, 'product_type' => ProductType::Rental,
        'field_key' => $field, 'field_type' => $field === 'capacity' ? 'number' : 'select',
        'options' => ['clown', 'magician'], 'is_required' => true,
        'validation_rules' => $field === 'capacity' ? ['min' => 1, 'max' => 12] : null]);
    $vendor = VendorProfile::factory()->create();
    $header = 'name_en,name_ar,short_description_en,short_description_ar,base_price_minor,requires_electricity,requires_outdoor_space,default_rental_duration_hours,category_id,'.$field;
    $row = 'Inflatable,لعبة هوائية,Party rental,تأجير حفلات,25000,0,0,4,'.$child->id.','.$value;
    $import = app(ImportRentalServicesFromExcelAction::class)->execute(UploadedFile::fake()->createWithContent('invalid-schema.csv', $header."\n".$row."\n"), $vendor->id);
    expect($import->status)->toBe('failed')->and($import->errors()->firstOrFail()->field)->toBe($field)
        ->and(Service::query()->count())->toBe(0);
})->with(['numeric bounds' => ['capacity', '50'], 'numeric type' => ['capacity', 'large'], 'select option' => ['act', 'unknown']]);

it('exposes inherited filterable schemas and applicable customer attributes', function (): void {
    app()->setLocale('en');
    [$root, $child, $schema] = categorySchemaFixture(ProductType::Rental, 'capacity', [
        'is_filterable' => true, 'field_label' => ['en' => 'Capacity', 'ar' => 'السعة'],
    ]);
    CategoryFieldSchema::factory()->create([
        'category_id' => $root->id, 'product_type' => ProductType::Rental, 'field_key' => 'theme',
        'field_type' => 'text', 'field_label' => ['en' => 'Theme', 'ar' => 'الطابع'],
        'is_filterable' => true, 'is_required' => false,
    ]);

    $response = $this->getJson('/api/v1/customer/categories/'.$child->public_id.'/field-schemas?type=rental');
    $response->assertOk()->assertJsonFragment(['field_key' => 'theme'])->assertJsonFragment(['field_key' => 'capacity']);

    $service = Service::factory()->rental()->create([
        'category_id' => $child->id,
        'category_field_values' => ['capacity' => 8, 'theme' => 'Birthday'],
    ]);
    $presented = app(CategoryFieldPresenter::class)->forService($service);
    expect($presented)->toHaveCount(2)
        ->and(collect($presented)->pluck('key')->all())->toContain('theme')->toContain('capacity');
    $json = (new CustomerServiceDetailResource($service->load(['category', 'vendor', 'rentalDetail'])))->toArray(request());
    expect($json['category_fields'])->toHaveCount(2)
        ->and(collect($json['category_fields'])->firstWhere('key', 'capacity')['value'])->toBe('8');
});

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @bodyParam name.en string optional (partial update) Service name in English. Example: "Birthday Bounce House"
 * @bodyParam name.ar string optional (partial update) Service name in Arabic. Example: "بيت الارتداد"
 * @bodyParam short_description.en string optional (partial update) Short description in English.
 * @bodyParam short_description.ar string optional (partial update) Short description in Arabic.
 * @bodyParam category_id integer optional (partial update) ID of the category. Example: 1
 * @bodyParam base_price_minor integer optional (partial update) Price in EGP piastres (100 = 1 EGP). Example: 150000
 * @bodyParam requires_electricity boolean optional (partial update) Whether requires electricity. Example: true
 * @bodyParam requires_outdoor_space boolean optional (partial update) Whether requires outdoor space. Example: false
 * @bodyParam default_rental_duration_hours integer optional (partial update) Default rental window in hours. Example: 6
 * @bodyParam setup_time_minutes integer optional (partial update) Setup time in minutes. Example: 60
 * @bodyParam teardown_time_minutes integer optional (partial update) Teardown time in minutes. Example: 30
 * @bodyParam security_deposit_minor integer optional (partial update) Refundable security deposit in piastres. Example: 50000
 * @bodyParam minimum_space_sqm integer optional (partial update) Minimum floor space required in square metres. Example: 25
 * @bodyParam length_m string optional Nullable positive length in metres, up to two decimals; null clears it, omission preserves it. Example: 4.50
 * @bodyParam width_m string optional Nullable positive width in metres, up to two decimals; null clears it, omission preserves it. Example: 3.25
 * @bodyParam height_m string optional Nullable positive height in metres, up to two decimals; null clears it, omission preserves it. Example: 2.00
 * @bodyParam category_field_values object optional Values for the selected category's dynamic fields; omission preserves them.
 */
class UpdateRentalServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'array'],
            'name.en' => ['sometimes', 'required', 'string', 'max:255'],
            'name.ar' => ['sometimes', 'required', 'string', 'max:255'],
            'short_description' => ['sometimes', 'required', 'array'],
            'short_description.en' => ['sometimes', 'required', 'string', 'max:500'],
            'short_description.ar' => ['sometimes', 'required', 'string', 'max:500'],
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereJsonContains('allowed_product_types', ProductType::Rental->value)),
            ],
            'base_price_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'requires_electricity' => ['sometimes', 'boolean'],
            'requires_outdoor_space' => ['sometimes', 'boolean'],
            'default_rental_duration_hours' => ['sometimes', 'required', 'integer', 'min:1', 'max:168'],
            'setup_time_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'teardown_time_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'security_deposit_minor' => ['sometimes', 'integer', 'min:0'],
            'minimum_space_sqm' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'length_m' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999.99', 'decimal:0,2'],
            'width_m' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999.99', 'decimal:0,2'],
            'height_m' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999.99', 'decimal:0,2'],
            'category_field_values' => ['sometimes', 'array'],
        ];
    }
}

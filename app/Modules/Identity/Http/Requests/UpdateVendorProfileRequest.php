<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Domain\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateVendorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'business_name' => ['sometimes', 'array'],
            'business_name.en' => ['sometimes', 'string', 'max:255'],
            'business_name.ar' => ['sometimes', 'string', 'max:255'],
            'bio' => ['sometimes', 'array'],
            // nullable: ConvertEmptyStringsToNull turns "" into null, so the
            // app's clear-bio payload {"bio":{"en":"","ar":""}} 422'd without
            // it (live audit 2026-06-06 §3.1). Empty string == clear.
            'bio.en' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'bio.ar' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'address_line' => ['sometimes', 'array', 'required_array_keys:en,ar'],
            'address_line.en' => ['required_with:address_line', 'string', 'max:255'],
            'address_line.ar' => ['required_with:address_line', 'string', 'max:255'],
            'business_type' => ['sometimes', new Enum(BusinessType::class)],
            'commercial_register_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bank_account_holder' => ['sometimes', 'nullable', 'string', 'max:160'],
            'bank_iban' => ['sometimes', 'nullable', 'string', 'max:34'],
            'bank_swift_bic' => ['sometimes', 'nullable', 'string', 'max:11'],
            'bank_branch' => ['sometimes', 'nullable', 'string', 'max:120'],
            'preferred_locale' => ['sometimes', 'in:en,ar'],
        ];
    }
}

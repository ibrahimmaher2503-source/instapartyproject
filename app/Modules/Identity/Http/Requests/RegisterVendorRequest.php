<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Application\DTOs\RegisterVendorDTO;
use App\Modules\Identity\Application\Services\VendorRegistrationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam phone_e164 string required Vendor contact number in E.164 format; spaces, hyphens, and parentheses are removed before validation. Example: +201001234567
 * @bodyParam primary_governorate_id integer required Initial governorate. Example: 1
 * @bodyParam primary_city_id integer Optional legacy city; when supplied it must belong to the governorate. City is completed after approval in the vendor profile. Example: 1
 * @bodyParam facebook_url string Optional Facebook page URL. Example: https://www.facebook.com/qa-events
 * @bodyParam website_url string Optional website URL. Example: https://qa-events.example
 * @bodyParam instagram_url string Optional Instagram page URL. Example: https://www.instagram.com/qa-events
 */
final class RegisterVendorRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(VendorRegistrationRules::normalize($this->all()));
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return VendorRegistrationRules::rules($this->integer('primary_governorate_id'));
    }

    public function messages(): array
    {
        return VendorRegistrationRules::messages();
    }

    public function attributes(): array
    {
        return VendorRegistrationRules::attributes();
    }

    public function toDTO(): RegisterVendorDTO
    {
        return RegisterVendorDTO::fromArray($this->validated());
    }
}

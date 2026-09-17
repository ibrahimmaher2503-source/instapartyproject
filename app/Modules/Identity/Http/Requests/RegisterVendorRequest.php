<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Application\DTOs\RegisterVendorDTO;
use App\Modules\Identity\Application\Services\VendorRegistrationRules;
use Illuminate\Foundation\Http\FormRequest;

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

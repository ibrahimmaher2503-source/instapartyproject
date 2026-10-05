<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Application\DTOs\UpdateCustomerProfileDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam gender string Optional new gender value: male or female. Omitted/null values preserve the stored value, including legacy records. Example: female
 */
class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'preferred_locale' => ['sometimes', 'string', 'in:en,ar'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female'],
            'accepts_marketing' => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(): UpdateCustomerProfileDTO
    {
        return UpdateCustomerProfileDTO::fromArray($this->validated());
    }
}

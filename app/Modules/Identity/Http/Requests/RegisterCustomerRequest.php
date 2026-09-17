<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Application\DTOs\RegisterCustomerDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_e164' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique('users', 'phone_e164')->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'preferred_locale' => ['nullable', 'string', 'in:en,ar'],
            'accepted_terms' => ['required', 'accepted'],
        ];
    }

    public function toDTO(): RegisterCustomerDTO
    {
        return RegisterCustomerDTO::fromArray($this->validated());
    }
}

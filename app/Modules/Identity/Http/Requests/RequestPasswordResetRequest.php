<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Application\DTOs\RequestPasswordResetDTO;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam identifier string required Customer email address or E.164 phone number. Example: sara@example.test
 */
class RequestPasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $trimmed = trim((string) $value);
                    $isEmail = (bool) filter_var($trimmed, FILTER_VALIDATE_EMAIL);
                    $isPhone = (bool) preg_match('/^\+\d{8,15}$/', $trimmed);
                    if (! $isEmail && ! $isPhone) {
                        $fail(__('auth.password_reset.invalid_identifier'));
                    }
                },
            ],
        ];
    }

    public function toDTO(): RequestPasswordResetDTO
    {
        return RequestPasswordResetDTO::fromArray([
            'identifier' => $this->input('identifier'),
            'locale' => app()->getLocale(),
        ]);
    }
}

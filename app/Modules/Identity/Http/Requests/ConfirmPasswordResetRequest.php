<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Application\DTOs\ConfirmPasswordResetDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam identifier string required Email or E.164 phone used during the request step. Example: sara@example.test
 * @bodyParam token string required Reset token (email link or 6-digit SMS code). Example: 123456
 * @bodyParam password string required New password (min 12 chars). Example: newpassword12
 * @bodyParam password_confirmation string required Must match `password`.
 */
class ConfirmPasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'token' => ['required', 'string', 'min:6', 'max:128'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }

    public function toDTO(): ConfirmPasswordResetDTO
    {
        return ConfirmPasswordResetDTO::fromArray([
            'identifier' => $this->input('identifier'),
            'token' => $this->input('token'),
            'password' => $this->input('password'),
        ]);
    }
}

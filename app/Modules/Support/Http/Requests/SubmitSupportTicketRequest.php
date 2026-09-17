<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isGuest = auth('sanctum')->guest();

        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'email' => [Rule::requiredIf($isGuest), 'nullable', 'email', 'max:255'],
            'booking_id' => ['nullable', 'string', 'max:26'],
        ];
    }
}

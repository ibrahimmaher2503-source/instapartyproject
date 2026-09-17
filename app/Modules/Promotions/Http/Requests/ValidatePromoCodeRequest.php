<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidatePromoCodeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50'],
            'booking_draft_id' => ['required', 'string', 'exists:bookings,public_id'],
            'cart_total_minor' => ['required', 'integer', 'min:1'],
            'cart_currency' => ['required', 'string', 'size:3'],
        ];
    }
}

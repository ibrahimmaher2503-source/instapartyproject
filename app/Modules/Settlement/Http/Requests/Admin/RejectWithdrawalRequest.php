<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectWithdrawalRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'array'],
            'reason.en' => ['required', 'string', 'max:1000'],
            'reason.ar' => ['required', 'string', 'max:1000'],
        ];
    }
}

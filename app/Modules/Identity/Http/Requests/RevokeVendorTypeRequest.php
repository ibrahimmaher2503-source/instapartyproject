<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RevokeVendorTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('revoke_vendor_type');
    }

    public function rules(): array
    {
        return [
            'product_type' => ['required', new Enum(ProductType::class)],
            'revoke_reason' => ['required', 'array', 'min:1'],
            'revoke_reason.en' => ['nullable', 'required_without:revoke_reason.ar', 'string', 'max:1000'],
            'revoke_reason.ar' => ['nullable', 'required_without:revoke_reason.en', 'string', 'max:1000'],
        ];
    }
}

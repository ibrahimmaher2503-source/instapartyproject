<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Application\Actions\VendorResubmitAfterChangesAction;
use Illuminate\Foundation\Http\FormRequest;

class RequestVendorChangesFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage_vendor_profile');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.field_path' => [
                'required',
                'string',
                'max:255',
                'distinct:strict',
                static function (string $attribute, mixed $value, $fail): void {
                    if (! is_string($value) || VendorResubmitAfterChangesAction::correctionField($value) === null) {
                        $fail(__('identity::identity.validation.unsupported_change_request_field'));
                    }
                },
            ],
            'items.*.requested_change_en' => ['required', 'string', 'min:5'],
            'items.*.requested_change_ar' => ['required', 'string', 'min:5'],
        ];
    }
}

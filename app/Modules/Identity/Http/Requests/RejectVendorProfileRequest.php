<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RejectVendorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('reject_vendor_profile');
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'array', 'min:1'],
            'rejection_reason.en' => ['nullable', 'required_without:rejection_reason.ar', 'string', 'max:1000'],
            'rejection_reason.ar' => ['nullable', 'required_without:rejection_reason.en', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => __('identity::identity.validation.rejection_reason_required'),
            'rejection_reason.en.required_without' => __('identity::identity.validation.rejection_reason_required'),
            'rejection_reason.ar.required_without' => __('identity::identity.validation.rejection_reason_required'),
        ];
    }
}

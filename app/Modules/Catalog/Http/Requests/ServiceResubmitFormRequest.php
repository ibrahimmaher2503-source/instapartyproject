<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class ServiceResubmitFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');
        $vendorProfile = $this->user()?->vendorProfile;

        abort_unless(
            $service instanceof Service
                && $vendorProfile !== null
                && (int) $service->vendor_profile_id === (int) $vendorProfile->getKey(),
            404,
        );

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'changed_fields' => ['required', 'array', 'min:1'],
            'changed_fields.*' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'changed_fields.required' => __('validation.required'),
            'changed_fields.array' => __('validation.array'),
        ];
    }

    /** @return array<string, mixed> */
    public function getChangedFields(): array
    {
        return (array) $this->input('changed_fields', []);
    }
}

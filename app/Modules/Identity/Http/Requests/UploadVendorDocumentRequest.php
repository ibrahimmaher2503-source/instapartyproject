<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Application\Services\RequiredVendorDocumentTypesResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * doc_type: An active vendor document type key (legacy code or admin-assigned key).
 * file: PDF, JPEG or PNG document; maximum 10 MB.
 */
class UploadVendorDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('vendor') ?? false;
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['required', 'string', Rule::in(array_keys(app(RequiredVendorDocumentTypesResolver::class)->options()))],
            'file' => [
                'required',
                'file',
                'max:10240', // 10 MB
                'mimetypes:application/pdf,image/jpeg,image/png',
                'mimes:pdf,jpg,jpeg,png',
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Http\Requests;

use App\Modules\TrustSafety\Domain\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitReportRequest extends FormRequest
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
            'reportable_type' => ['required', 'string', Rule::in(['vendor_profile', 'chat_thread'])],
            'reportable_id' => ['required', 'string'],
            'reason' => ['required', 'string', Rule::in(array_column(ReportReason::cases(), 'value'))],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

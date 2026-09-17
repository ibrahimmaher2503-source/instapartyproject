<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Http\Resources;

use App\Modules\TrustSafety\Domain\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Report
 *
 * @response 201 {
 *   "data": {
 *     "public_id": "01HW...",
 *     "status": "open",
 *     "message": "Your report has been submitted. Our team will review it shortly."
 *   }
 * }
 */
class ReportConfirmationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $this->resolveLocale($request);

        return [
            'public_id' => $this->public_id,
            'status' => $this->status->value,
            'message' => $locale === 'ar'
                ? 'تم تقديم بلاغك. سيقوم فريقنا بمراجعته قريبًا.'
                : 'Your report has been submitted. Our team will review it shortly.',
        ];
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $request->header('Accept-Language', 'en');

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }
}

<?php

declare(strict_types=1);

use App\Modules\TrustSafety\Domain\Enums\ReportReason;
use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
use App\Modules\TrustSafety\Filament\Resources\ReportResource;

it('translates supported report target, reason, and status labels in English and Arabic', function (string $locale, string $target, string $reason, string $status): void {
    app()->setLocale($locale);

    expect(ReportResource::targetTypeLabel('vendor_profile'))->toBe($target)
        ->and(ReportReason::Other->label())->toBe($reason)
        ->and(ReportStatus::UnderReview->label())->toBe($status);
})->with([
    'English' => ['en', 'Vendor Profile', 'Other', 'Under Review'],
    'Arabic' => ['ar', 'ملف مقدم الخدمة', 'أخرى', 'قيد المراجعة'],
]);

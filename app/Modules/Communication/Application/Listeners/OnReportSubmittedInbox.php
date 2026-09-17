<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\TrustSafety\Domain\Events\ReportSubmitted;

class OnReportSubmittedInbox
{
    public function __construct(
        private readonly RouteToAdminInboxAction $router,
    ) {}

    public function handle(ReportSubmitted $event): void
    {
        $report = $event->report;
        $reason = $report->reason->value;

        $this->router->execute(
            eventKey: 'trust_safety.report_submitted',
            severity: AdminInboxSeverity::Warning,
            sourceType: 'report',
            sourceId: $report->id,
            title: [
                'en' => "New {$reason} report — #{$report->public_id}",
                'ar' => "بلاغ {$reason} جديد — #{$report->public_id}",
            ],
            body: [
                'en' => "Report #{$report->public_id} filed by user #{$report->reporter_id}. Reason: {$reason}. Review required.",
                'ar' => "البلاغ #{$report->public_id} مقدم من المستخدم #{$report->reporter_id}. السبب: {$reason}. يلزم المراجعة.",
            ],
        );
    }
}

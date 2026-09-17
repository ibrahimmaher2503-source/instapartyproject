<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\TrustSafety\Domain\Events\ReportSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnReportSubmittedCustomerAck implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(ReportSubmitted $event): void
    {
        $report = $event->report;

        $context = [
            'report_id' => $report->public_id,
        ];

        foreach ([NotificationChannel::Email, NotificationChannel::InApp] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'report.submitted.ack',
                channel: $channel,
                audience: NotificationAudience::Customer,
                eventCategory: EventCategory::System,
                userId: $report->reporter_id,
                context: $context,
                referenceType: 'report',
                referenceId: $report->id,
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Support\Domain\Events\SupportTicketCreated;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class OnSupportTicketCreatedInbox implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly RouteToAdminInboxAction $router) {}

    public function handle(SupportTicketCreated $event): void
    {
        $ticket = $event->ticket;
        $reference = sprintf('TK-%s-%05d', $ticket->created_at->format('Y'), $ticket->id);

        $this->router->execute(
            eventKey: 'support.ticket.created',
            severity: AdminInboxSeverity::Info,
            sourceType: 'support_ticket',
            sourceId: $ticket->id,
            title: ['en' => "New support ticket {$reference}", 'ar' => "تذكرة دعم جديدة {$reference}"],
            body: ['en' => "Support ticket {$reference} is awaiting review.", 'ar' => "تذكرة الدعم {$reference} في انتظار المراجعة."],
        );
    }
}

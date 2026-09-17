<?php

declare(strict_types=1);

namespace App\Modules\Support\Domain\Events;

use App\Modules\Support\Domain\Models\SupportTicket;

final readonly class SupportTicketCreated
{
    public function __construct(
        public SupportTicket $ticket,
    ) {}
}

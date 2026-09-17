<?php

declare(strict_types=1);

namespace App\Modules\Support\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum SupportTicketStatus: string implements HasLabel
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return __('support::support.ticket_status.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Support\Infrastructure\Repositories;

use App\Modules\Support\Application\DTOs\SubmitSupportTicketDTO;
use App\Modules\Support\Domain\Models\SupportTicket;

class EloquentSupportTicketRepository
{
    public function create(SubmitSupportTicketDTO $dto): SupportTicket
    {
        return SupportTicket::create([
            'user_id' => $dto->userId,
            'email' => $dto->email,
            'subject' => $dto->subject,
            'body' => $dto->body,
        ]);
    }

    public function generateReference(SupportTicket $ticket): string
    {
        return sprintf('TK-%s-%05d', date('Y'), $ticket->id);
    }
}

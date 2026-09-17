<?php

declare(strict_types=1);

namespace App\Modules\Support\Application\Actions;

use App\Modules\Support\Domain\Enums\SupportTicketStatus;
use App\Modules\Support\Domain\Models\SupportTicket;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

class TransitionSupportTicketStatusAction
{
    public function execute(
        SupportTicket $ticket,
        Authenticatable $actor,
        SupportTicketStatus $target,
    ): SupportTicket {
        Gate::forUser($actor)->authorize('update', $ticket);

        return DB::transaction(function () use ($ticket, $actor, $target): SupportTicket {
            $lockedTicket = SupportTicket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $current = $lockedTicket->status;
            $isOpening = $current === SupportTicketStatus::Open
                && $target === SupportTicketStatus::InProgress;
            $isResolving = $current === SupportTicketStatus::InProgress
                && $target === SupportTicketStatus::Resolved;

            if (! $isOpening && ! $isResolving) {
                throw new LogicException("Cannot transition support ticket from {$current->value} to {$target->value}.");
            }

            $attributes = ['status' => $target];

            if ($isOpening) {
                $attributes['assigned_to'] = (int) $actor->getAuthIdentifier();
            }

            $lockedTicket->update($attributes);

            return $lockedTicket->refresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Support\Application\Actions;

use App\Modules\Support\Application\DTOs\SubmitSupportTicketDTO;
use App\Modules\Support\Domain\Events\SupportTicketCreated;
use App\Modules\Support\Domain\Models\SupportTicket;
use App\Modules\Support\Infrastructure\Repositories\EloquentSupportTicketRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SubmitSupportTicketAction
{
    public function __construct(
        private readonly EloquentSupportTicketRepository $repository,
    ) {}

    public function execute(SubmitSupportTicketDTO $dto): SupportTicket
    {
        $cacheKey = $this->idempotencyKey($dto);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return SupportTicket::findOrFail($cached);
        }

        $ticket = DB::transaction(function () use ($dto) {
            $ticket = $this->repository->create($dto);

            DB::afterCommit(fn () => event(new SupportTicketCreated($ticket)));

            return $ticket;
        });

        Cache::put($cacheKey, $ticket->id, now()->addHours(24));

        return $ticket;
    }

    private function idempotencyKey(SubmitSupportTicketDTO $dto): string
    {
        $hash = hash('sha256', implode('|', [
            $dto->userId ?? 'guest',
            $dto->email ?? '',
            $dto->subject,
            $dto->body,
        ]));

        return "support_ticket_idempotency:{$hash}";
    }
}

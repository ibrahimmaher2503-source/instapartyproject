<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Listeners;

use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Settlement\Application\Actions\ReconcileCompletedRefundsAction;
use Illuminate\Contracts\Queue\ShouldQueue;

class ReverseCommissionOnRefundCompletedListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private readonly ReconcileCompletedRefundsAction $reconcileRefunds,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(RefundCompleted $event): void
    {
        $this->reconcileRefunds->execute($event->paymentId);
    }
}

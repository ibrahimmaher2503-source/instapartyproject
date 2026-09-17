<?php

declare(strict_types=1);

namespace App\Modules\Communication\Console;

use App\Modules\Communication\Application\Actions\RetryFailedDispatchAction;
use App\Modules\Communication\Application\Exceptions\DispatchNotRetryableException;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Console\Command;

class RetryFailedDispatchesCommand extends Command
{
    protected $signature = 'communication:retry-failed-dispatches';

    protected $description = 'Recover stale queued notifications and re-queue failed dispatches that are due.';

    public function handle(RetryFailedDispatchAction $action): int
    {
        $staleIds = NotificationDispatch::staleQueued()->limit(1000)->pluck('id');

        $staleCount = 0;

        if ($staleIds->isNotEmpty()) {
            $staleCount = NotificationDispatch::query()
                ->whereKey($staleIds)
                ->where('status', DispatchStatus::Queued->value)
                ->update([
                    'status' => DispatchStatus::Failed->value,
                    'provider_error_code' => 'STALE_QUEUED',
                    'provider_error_message' => 'Queued dispatch was not claimed within 15 minutes.',
                    'next_retry_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $dispatches = NotificationDispatch::retryable()->limit(1000)->get();
        $requeued = 0;
        $skipped = 0;

        foreach ($dispatches as $dispatch) {
            try {
                $action->execute(
                    $dispatch,
                    null,
                    $staleIds->contains($dispatch->id)
                        ? 'notification.stale_dispatch_requeued'
                        : 'notification.auto_retry_queued',
                );
                $requeued++;
            } catch (DispatchNotRetryableException) {
                // Cap-reached or state race — audit already logged inside the action
                $skipped++;
            }
        }

        $message = "Recovered {$staleCount} stale queued dispatch(es); re-queued {$requeued} dispatch(es).";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} (cap reached or state race).";
        }

        $this->info($message);

        return self::SUCCESS;
    }
}

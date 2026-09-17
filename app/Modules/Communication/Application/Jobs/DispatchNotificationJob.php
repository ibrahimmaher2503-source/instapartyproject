<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Jobs;

use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DispatchNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public int $backoff = 0;

    public function __construct(public readonly int $dispatchId) {}

    public function handle(Container $container): void
    {
        $claimed = NotificationDispatch::query()
            ->whereKey($this->dispatchId)
            ->where('status', DispatchStatus::Queued->value)
            ->where(function ($query): void {
                $query->whereNull('provider_status')->orWhere('provider_status', '!=', 'processing');
            })
            ->update([
                'provider_status' => 'processing',
                'attempt_count' => DB::raw('attempt_count + 1'),
                'last_attempt_at' => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $dispatch = NotificationDispatch::query()->findOrFail($this->dispatchId);

        $adapter = $container->make($dispatch->channel->value.'_adapter');

        try {
            $adapter->send($dispatch);
        } catch (Throwable $e) {
            // Adapter violated its contract (MUST NOT throw — see NotificationChannelAdapter).
            // Record the failure so the dispatch isn't stuck in Queued forever.
            $dispatch->refresh()->update([
                'status' => DispatchStatus::Failed->value,
                'provider_error_code' => 'ADAPTER_THREW',
                'provider_error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }
    }
}

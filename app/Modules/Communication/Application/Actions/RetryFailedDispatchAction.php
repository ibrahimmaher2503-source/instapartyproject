<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\Exceptions\DispatchNotRetryableException;
use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Events\NotificationDispatchRetried;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetryFailedDispatchAction
{
    public function execute(
        NotificationDispatch $dispatch,
        ?int $adminUserId = null,
        string $auditAction = 'notification.retry_queued',
    ): NotificationDispatch {
        try {
            return DB::transaction(function () use ($dispatch, $adminUserId, $auditAction) {
                $dispatch = NotificationDispatch::lockForUpdate()->findOrFail($dispatch->id);

                if ($dispatch->status !== DispatchStatus::Failed) {
                    throw new DispatchNotRetryableException($dispatch, 'not_failed');
                }

                if ($dispatch->attempt_count >= 5) {
                    throw new DispatchNotRetryableException($dispatch, 'cap_reached');
                }

                $previousAttemptCount = $dispatch->attempt_count;
                $previousErrorCode = $dispatch->provider_error_code;

                $dispatch->update([
                    'status' => DispatchStatus::Queued,
                    'next_retry_at' => now()->addMinutes(15),
                    'provider_status' => null,
                    'provider_error_code' => null,
                    'provider_error_message' => null,
                ]);

                $this->writeAudit($dispatch, $adminUserId, $auditAction, [
                    'attempt_count' => $previousAttemptCount,
                    'provider_name' => $dispatch->provider_name,
                    'previous_error_code' => $previousErrorCode,
                ]);

                DB::afterCommit(function () use ($dispatch, $previousAttemptCount, $adminUserId) {
                    DispatchNotificationJob::dispatch($dispatch->id)->onQueue('notifications');
                    event(new NotificationDispatchRetried($dispatch, $previousAttemptCount, $adminUserId));
                });

                return $dispatch;
            });
        } catch (DispatchNotRetryableException $e) {
            // Write outside the rolled-back transaction so the audit row survives.
            // Race-safe: the cap_reached decision was made inside lockForUpdate.
            if ($e->reason === 'cap_reached') {
                $this->writeAudit($e->dispatch, $adminUserId, 'notification.retry_cap_reached', [
                    'attempt_count' => $e->dispatch->attempt_count,
                    'provider_name' => $e->dispatch->provider_name,
                ]);
            }

            throw $e;
        }
    }

    /** @param array<string, mixed> $changes */
    private function writeAudit(NotificationDispatch $dispatch, ?int $adminUserId, string $action, array $changes): void
    {
        DB::table('audit_logs')->insert([
            'public_id' => (string) Str::ulid(),
            'auditable_type' => NotificationDispatch::class,
            'auditable_id' => $dispatch->id,
            'action' => $action,
            'user_id' => $adminUserId,
            'changes' => json_encode($changes),
            'created_at' => now(),
        ]);
    }
}

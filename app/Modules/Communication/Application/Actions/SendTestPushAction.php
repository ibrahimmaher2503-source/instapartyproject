<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\TestPushDTO;
use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendTestPushAction
{
    public function execute(TestPushDTO $dto): NotificationDispatch
    {
        return DB::transaction(function () use ($dto): NotificationDispatch {
            $dispatch = NotificationDispatch::create([
                'public_id' => Str::ulid()->toBase32(),
                'user_id' => $dto->adminUserId,
                'channel' => NotificationChannel::Push->value,
                'locale' => 'en',
                'status' => DispatchStatus::Queued->value,
                'attempt_count' => 0,
                'is_test' => true,
                'context' => [
                    'device_token' => $dto->deviceToken,
                    'title' => 'InstaParty test push',
                    'body' => $dto->bodyOverride ?? 'Test from health page',
                ],
            ]);

            DB::afterCommit(function () use ($dispatch): void {
                DispatchNotificationJob::dispatch($dispatch->id);
            });

            DB::afterCommit(function () use ($dispatch, $dto): void {
                DB::table('audit_logs')->insert([
                    'public_id' => Str::ulid()->toBase32(),
                    'auditable_type' => NotificationDispatch::class,
                    'auditable_id' => $dispatch->id,
                    'action' => 'notification.test_sent',
                    'user_id' => $dto->adminUserId,
                    'changes' => json_encode([
                        'channel' => 'push',
                        'recipient' => 'token:'.substr($dto->deviceToken, -4),
                        'admin_user_id' => $dto->adminUserId,
                    ]),
                    'created_at' => now(),
                ]);
            });

            return $dispatch;
        });
    }
}

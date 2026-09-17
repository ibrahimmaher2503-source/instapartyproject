<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\TestSmsDTO;
use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendTestSmsAction
{
    public function execute(TestSmsDTO $dto): NotificationDispatch
    {
        return DB::transaction(function () use ($dto): NotificationDispatch {
            $body = $dto->bodyOverride ?? 'InstaParty test SMS';

            $dispatch = NotificationDispatch::create([
                'public_id' => Str::ulid()->toBase32(),
                'user_id' => $dto->adminUserId,
                'channel' => NotificationChannel::Sms->value,
                'locale' => 'en',
                'status' => DispatchStatus::Queued->value,
                'attempt_count' => 0,
                'is_test' => true,
                'context' => [
                    'phone_e164' => $dto->phoneE164,
                    'sms_body' => $body,
                    'body' => $body,
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
                        'channel' => 'sms',
                        'recipient' => 'phone:+***'.substr($dto->phoneE164, -4),
                        'admin_user_id' => $dto->adminUserId,
                    ]),
                    'created_at' => now(),
                ]);
            });

            return $dispatch;
        });
    }
}

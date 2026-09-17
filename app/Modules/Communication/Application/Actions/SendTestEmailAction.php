<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\TestEmailDTO;
use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendTestEmailAction
{
    public function execute(TestEmailDTO $dto): NotificationDispatch
    {
        return DB::transaction(function () use ($dto): NotificationDispatch {
            $body = $dto->bodyOverride ?? 'This is a test from the InstaParty health page.';
            $subject = $dto->subjectOverride ?? 'InstaParty test email';

            $dispatch = NotificationDispatch::create([
                'public_id' => Str::ulid()->toBase32(),
                'user_id' => $dto->adminUserId,
                'channel' => NotificationChannel::Email->value,
                'locale' => 'en',
                'status' => DispatchStatus::Queued->value,
                'attempt_count' => 0,
                'is_test' => true,
                'context' => [
                    'email' => $dto->emailAddress,
                    'subject' => $subject,
                    'email_body' => $body,
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
                        'channel' => 'email',
                        'recipient' => $this->maskEmail($dto->emailAddress),
                        'admin_user_id' => $dto->adminUserId,
                    ]),
                    'created_at' => now(),
                ]);
            });

            return $dispatch;
        });
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2) {
            return '***@***';
        }

        [$local, $domain] = $parts;
        $masked = substr($local, 0, 1).'***';

        return $masked.'@'.$domain;
    }
}

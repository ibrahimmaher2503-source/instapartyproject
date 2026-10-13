<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Listeners;

use App\Modules\Communication\Domain\Contracts\EmailTransportReadiness;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Events\PasswordResetRequested;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendPasswordResetNotification implements ShouldBeEncrypted, ShouldQueue
{
    public string $queue = 'notifications';

    public bool $afterCommit = true;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(
        private readonly OtpGatewayInterface $smsGateway,
        private readonly EmailTransportReadiness $emailReadiness,
    ) {}

    public function handle(PasswordResetRequested $event): void
    {
        if ($event->channel === 'email') {
            $this->sendEmail($event->identifier, $event->tokenPlain, $event->locale);

            return;
        }

        $this->smsGateway->send($event->identifier, $event->tokenPlain);
    }

    private function sendEmail(string $email, string $token, string $locale): void
    {
        if (! app()->environment(['local', 'testing']) && ! $this->emailReadiness->isConfigured()) {
            Log::warning('password-reset.email.unavailable', [
                'failure_code' => 'MAIL_NOT_CONFIGURED',
            ]);

            throw new RuntimeException('MAIL_NOT_CONFIGURED');
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $resetUrl = "{$appUrl}/{$locale}/auth/reset?token={$token}&identifier=".urlencode($email);

        try {
            Mail::raw(
                __('auth.password_reset.email_body', ['url' => $resetUrl], $locale),
                function ($message) use ($email, $locale): void {
                    $message->to($email)
                        ->subject(__('auth.password_reset.email_subject', [], $locale));
                },
            );
        } catch (Throwable $e) {
            Log::warning('password-reset.email.failed', [
                'error_class' => class_basename($e),
            ]);

            throw new RuntimeException('EMAIL_DELIVERY_FAILED');
        }
    }
}
